using System.Text;
using System.Text.Json;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;
using Powersa.LogoWorker.Configuration;
using Powersa.LogoWorker.Models;
using RabbitMQ.Client;
using RabbitMQ.Client.Events;

namespace Powersa.LogoWorker.Services;

public sealed class RabbitMqConsumerService : BackgroundService
{
    private readonly RabbitMqOptions _options;
    private readonly WorkerOptions _workerOptions;
    private readonly LogoEventDispatcher _dispatcher;
    private readonly ILogger<RabbitMqConsumerService> _logger;

    public RabbitMqConsumerService(
        IOptions<RabbitMqOptions> options,
        IOptions<WorkerOptions> workerOptions,
        LogoEventDispatcher dispatcher,
        ILogger<RabbitMqConsumerService> logger)
    {
        _options = options.Value;
        _workerOptions = workerOptions.Value;
        _dispatcher = dispatcher;
        _logger = logger;
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        var factory = new ConnectionFactory
        {
            HostName = _options.Host,
            Port = _options.Port,
            UserName = _options.Username,
            Password = _options.Password,
            VirtualHost = _options.VirtualHost
        };

        await using var connection = await factory.CreateConnectionAsync(stoppingToken);
        await using var channel = await connection.CreateChannelAsync(cancellationToken: stoppingToken);

        await channel.ExchangeDeclareAsync(_options.Exchange, ExchangeType.Topic, durable: true, cancellationToken: stoppingToken);
        await channel.QueueDeclareAsync(_options.QueueName, durable: true, exclusive: false, autoDelete: false, cancellationToken: stoppingToken);
        await channel.BasicQosAsync(0, _workerOptions.PrefetchCount, false, stoppingToken);

        foreach (var routingKey in _options.RoutingKeys)
        {
            await channel.QueueBindAsync(_options.QueueName, _options.Exchange, routingKey, cancellationToken: stoppingToken);
        }

        var consumer = new AsyncEventingBasicConsumer(channel);
        consumer.ReceivedAsync += async (_, eventArgs) =>
        {
            var body = Encoding.UTF8.GetString(eventArgs.Body.ToArray());

            try
            {
                var envelope = JsonSerializer.Deserialize<LogoWriteEnvelope>(body, new JsonSerializerOptions
                {
                    PropertyNameCaseInsensitive = true
                }) ?? throw new InvalidOperationException("Envelope parse edilemedi.");

                _logger.LogInformation("Consuming event_id={EventId} type={EventType}", envelope.EventId, envelope.EventType);

                await _dispatcher.DispatchAsync(envelope, stoppingToken);
                await channel.BasicAckAsync(eventArgs.DeliveryTag, false, stoppingToken);
            }
            catch (Exception exception)
            {
                _logger.LogError(exception, "Rabbit message processing failed.");
                await channel.BasicNackAsync(eventArgs.DeliveryTag, false, requeue: true, cancellationToken: stoppingToken);
            }
        };

        await channel.BasicConsumeAsync(_options.QueueName, autoAck: false, consumer: consumer, cancellationToken: stoppingToken);

        while (!stoppingToken.IsCancellationRequested)
        {
            await Task.Delay(1000, stoppingToken);
        }
    }
}
