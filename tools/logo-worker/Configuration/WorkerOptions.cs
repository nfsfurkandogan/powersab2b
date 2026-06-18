namespace Powersa.LogoWorker.Configuration;

public sealed class WorkerOptions
{
    public ushort PrefetchCount { get; set; } = 1;
    public int MaxRetryAttempts { get; set; } = 5;
}
