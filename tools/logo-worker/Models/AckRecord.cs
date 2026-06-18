namespace Powersa.LogoWorker.Models;

public sealed class AckRecord
{
    public int? CustomerId { get; set; }
    public int? CollectionId { get; set; }
    public string Status { get; set; } = "failed";
    public string? ExternalRef { get; set; }
    public string? Error { get; set; }
    public Dictionary<string, object?> Meta { get; set; } = [];
}
