namespace Powersa.LogoWorker.Configuration;

public sealed class LogoOptions
{
    public string WriteMode { get; set; } = "StoredProcedure";
    public int CompanyNo { get; set; } = 2;
    public int PeriodNo { get; set; } = 1;
    public string Username { get; set; } = string.Empty;
    public string Password { get; set; } = string.Empty;
    public string MachineName { get; set; } = string.Empty;
    public string SqlConnectionString { get; set; } = string.Empty;
    public string CustomerCreateProcedure { get; set; } = string.Empty;
    public string CustomerUpdateProcedure { get; set; } = string.Empty;
    public string CollectionCreateProcedure { get; set; } = string.Empty;
}
