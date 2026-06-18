using System.Text.Json;
using System.Text.Json.Serialization;

namespace Powersa.LogoWorker.Models;

public sealed class LogoWriteEnvelope
{
    [JsonPropertyName("event_id")]
    public string EventId { get; set; } = string.Empty;

    [JsonPropertyName("event_type")]
    public string EventType { get; set; } = string.Empty;

    [JsonPropertyName("routing_key")]
    public string RoutingKey { get; set; } = string.Empty;

    [JsonPropertyName("exchange")]
    public string Exchange { get; set; } = string.Empty;

    [JsonPropertyName("occurred_at")]
    public string OccurredAt { get; set; } = string.Empty;

    [JsonPropertyName("dealer_id")]
    public int? DealerId { get; set; }

    [JsonPropertyName("entity_type")]
    public string EntityType { get; set; } = string.Empty;

    [JsonPropertyName("entity_id")]
    public int EntityId { get; set; }

    [JsonPropertyName("idempotency_key")]
    public string IdempotencyKey { get; set; } = string.Empty;

    [JsonPropertyName("payload")]
    public JsonElement Payload { get; set; }
}
