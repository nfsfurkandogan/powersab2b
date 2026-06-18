namespace Powersa.LogoWorker.Configuration;

public sealed class B2BApiOptions
{
    public string BaseUrl { get; set; } = string.Empty;
    public string CustomerAckPath { get; set; } = "/api/integrations/logo/customers/ack";
    public string CollectionAckPath { get; set; } = "/api/integrations/logo/collections/ack";
    public string CustomerSyncKey { get; set; } = string.Empty;
    public string CollectionSyncKey { get; set; } = string.Empty;
}
