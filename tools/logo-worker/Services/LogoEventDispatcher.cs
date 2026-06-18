using System.Text.Json;
using Microsoft.Extensions.Logging;
using Powersa.LogoWorker.Models;

namespace Powersa.LogoWorker.Services;

public sealed class LogoEventDispatcher
{
    private readonly LogoObjectsGateway _gateway;
    private readonly ResultReporter _reporter;
    private readonly ILogger<LogoEventDispatcher> _logger;

    public LogoEventDispatcher(
        LogoObjectsGateway gateway,
        ResultReporter reporter,
        ILogger<LogoEventDispatcher> logger)
    {
        _gateway = gateway;
        _reporter = reporter;
        _logger = logger;
    }

    public async Task DispatchAsync(LogoWriteEnvelope envelope, CancellationToken cancellationToken)
    {
        switch (envelope.EventType)
        {
            case "logo.customer.create":
            {
                var payload = envelope.Payload.Deserialize<CustomerWritePayload>(JsonOptions()) ?? throw new InvalidOperationException("Customer payload deserializable degil.");
                var externalRef = await _gateway.CreateCustomerAsync(payload, cancellationToken);
                await _reporter.ReportCustomerAsync(new AckRecord
                {
                    CustomerId = payload.CustomerId,
                    Status = "synced",
                    ExternalRef = externalRef,
                    Meta = new Dictionary<string, object?> { ["event_id"] = envelope.EventId, ["event_type"] = envelope.EventType }
                }, cancellationToken);
                return;
            }
            case "logo.customer.update":
            {
                var payload = envelope.Payload.Deserialize<CustomerWritePayload>(JsonOptions()) ?? throw new InvalidOperationException("Customer payload deserializable degil.");
                var externalRef = await _gateway.UpdateCustomerAsync(payload, cancellationToken);
                await _reporter.ReportCustomerAsync(new AckRecord
                {
                    CustomerId = payload.CustomerId,
                    Status = "synced",
                    ExternalRef = externalRef,
                    Meta = new Dictionary<string, object?> { ["event_id"] = envelope.EventId, ["event_type"] = envelope.EventType }
                }, cancellationToken);
                return;
            }
            case "logo.collection.create":
            {
                var payload = envelope.Payload.Deserialize<CollectionWritePayload>(JsonOptions()) ?? throw new InvalidOperationException("Collection payload deserializable degil.");
                var externalRef = await _gateway.CreateCollectionAsync(payload, cancellationToken);
                await _reporter.ReportCollectionAsync(new AckRecord
                {
                    CollectionId = payload.CollectionId,
                    Status = "synced",
                    ExternalRef = externalRef,
                    Meta = new Dictionary<string, object?> { ["event_id"] = envelope.EventId, ["event_type"] = envelope.EventType }
                }, cancellationToken);
                return;
            }
            default:
                _logger.LogWarning("Unsupported event type={EventType}", envelope.EventType);
                throw new NotSupportedException($"Unsupported event type: {envelope.EventType}");
        }
    }

    private static JsonSerializerOptions JsonOptions()
    {
        return new JsonSerializerOptions
        {
            PropertyNameCaseInsensitive = true
        };
    }
}
