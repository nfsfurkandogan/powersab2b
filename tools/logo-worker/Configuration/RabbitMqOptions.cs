namespace Powersa.LogoWorker.Configuration;

public sealed class RabbitMqOptions
{
    public string Host { get; set; } = "127.0.0.1";
    public int Port { get; set; } = 5672;
    public string Username { get; set; } = "guest";
    public string Password { get; set; } = "guest";
    public string VirtualHost { get; set; } = "/";
    public string Exchange { get; set; } = "powersa.logo";
    public string QueueName { get; set; } = "logo.write.worker";
    public List<string> RoutingKeys { get; set; } = [];
}
