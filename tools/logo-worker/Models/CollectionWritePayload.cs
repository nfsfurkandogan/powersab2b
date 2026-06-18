using System.Text.Json.Serialization;

namespace Powersa.LogoWorker.Models;

public sealed class CollectionWritePayload
{
    [JsonPropertyName("collection_id")]
    public int CollectionId { get; set; }

    [JsonPropertyName("export_key")]
    public string? ExportKey { get; set; }

    [JsonPropertyName("dealer_id")]
    public int? DealerId { get; set; }

    [JsonPropertyName("customer_id")]
    public int CustomerId { get; set; }

    [JsonPropertyName("customer_code")]
    public string? CustomerCode { get; set; }

    [JsonPropertyName("customer_external_ref")]
    public string? CustomerExternalRef { get; set; }

    [JsonPropertyName("date")]
    public string? Date { get; set; }

    [JsonPropertyName("method")]
    public string Method { get; set; } = string.Empty;

    [JsonPropertyName("amount")]
    public string Amount { get; set; } = string.Empty;

    [JsonPropertyName("currency")]
    public string Currency { get; set; } = "TRY";

    [JsonPropertyName("reference_no")]
    public string? ReferenceNo { get; set; }

    [JsonPropertyName("note")]
    public string? Note { get; set; }

    [JsonPropertyName("cashbox_id")]
    public int? CashboxId { get; set; }

    [JsonPropertyName("cashbox_code")]
    public string? CashboxCode { get; set; }

    [JsonPropertyName("cashbox_name")]
    public string? CashboxName { get; set; }
}
