using System.Text.Json.Serialization;

namespace Powersa.LogoWorker.Models;

public sealed class CustomerWritePayload
{
    [JsonPropertyName("customer_id")]
    public int CustomerId { get; set; }

    [JsonPropertyName("export_key")]
    public string? ExportKey { get; set; }

    [JsonPropertyName("dealer_id")]
    public int? DealerId { get; set; }

    [JsonPropertyName("customer_code")]
    public string CustomerCode { get; set; } = string.Empty;

    [JsonPropertyName("name")]
    public string Name { get; set; } = string.Empty;

    [JsonPropertyName("contact_name")]
    public string? ContactName { get; set; }

    [JsonPropertyName("email")]
    public string? Email { get; set; }

    [JsonPropertyName("phone")]
    public string? Phone { get; set; }

    [JsonPropertyName("city")]
    public string? City { get; set; }

    [JsonPropertyName("district")]
    public string? District { get; set; }

    [JsonPropertyName("tax_office")]
    public string? TaxOffice { get; set; }

    [JsonPropertyName("tax_number")]
    public string? TaxNumber { get; set; }

    [JsonPropertyName("credit_limit")]
    public string? CreditLimit { get; set; }

    [JsonPropertyName("is_active")]
    public bool IsActive { get; set; }

    [JsonPropertyName("address")]
    public string? Address { get; set; }

    [JsonPropertyName("iban")]
    public string? Iban { get; set; }

    [JsonPropertyName("source_reference")]
    public string? SourceReference { get; set; }
}
