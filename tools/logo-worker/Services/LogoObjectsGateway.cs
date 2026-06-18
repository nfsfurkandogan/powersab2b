using System.Data;
using System.Text.Json;
using Microsoft.Data.SqlClient;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;
using Powersa.LogoWorker.Configuration;
using Powersa.LogoWorker.Models;

namespace Powersa.LogoWorker.Services;

public sealed class LogoObjectsGateway
{
    private const string SupportedWriteMode = "StoredProcedure";
    private readonly LogoOptions _options;
    private readonly ILogger<LogoObjectsGateway> _logger;

    public LogoObjectsGateway(
        IOptions<LogoOptions> options,
        ILogger<LogoObjectsGateway> logger)
    {
        _options = options.Value;
        _logger = logger;
    }

    public Task<string> CreateCustomerAsync(CustomerWritePayload payload, CancellationToken cancellationToken)
    {
        _logger.LogInformation("Logo customer create requested for customer_id={CustomerId} code={CustomerCode}", payload.CustomerId, payload.CustomerCode);

        EnsureSupportedWriteMode();
        return ExecuteCustomerProcedureAsync(_options.CustomerCreateProcedure, payload, cancellationToken);
    }

    public Task<string> UpdateCustomerAsync(CustomerWritePayload payload, CancellationToken cancellationToken)
    {
        _logger.LogInformation("Logo customer update requested for customer_id={CustomerId} code={CustomerCode}", payload.CustomerId, payload.CustomerCode);

        EnsureSupportedWriteMode();
        return ExecuteCustomerProcedureAsync(_options.CustomerUpdateProcedure, payload, cancellationToken);
    }

    public Task<string> CreateCollectionAsync(CollectionWritePayload payload, CancellationToken cancellationToken)
    {
        _logger.LogInformation("Logo collection create requested for collection_id={CollectionId} customer_code={CustomerCode}", payload.CollectionId, payload.CustomerCode);

        EnsureSupportedWriteMode();
        return ExecuteCollectionProcedureAsync(_options.CollectionCreateProcedure, payload, cancellationToken);
    }

    private void EnsureSupportedWriteMode()
    {
        if (string.Equals(_options.WriteMode, SupportedWriteMode, StringComparison.OrdinalIgnoreCase))
        {
            return;
        }

        throw new InvalidOperationException("Logo Objects modu devre disi. Yazma lisansi icin yalnizca Logo:WriteMode=StoredProcedure SQL kopru modu desteklenir.");
    }

    private async Task<string> ExecuteCustomerProcedureAsync(
        string procedureName,
        CustomerWritePayload payload,
        CancellationToken cancellationToken)
    {
        EnsureStoredProcedureOptions(procedureName);

        await using var connection = new SqlConnection(_options.SqlConnectionString);
        await connection.OpenAsync(cancellationToken);

        await using var command = CreateCommand(connection, procedureName);
        AddString(command, "@CustomerCode", payload.CustomerCode, 64);
        AddString(command, "@Name", payload.Name, 255);
        AddString(command, "@ContactName", payload.ContactName, 120);
        AddString(command, "@Email", payload.Email, 160);
        AddString(command, "@Phone", payload.Phone, 64);
        AddString(command, "@City", payload.City, 80);
        AddString(command, "@District", payload.District, 80);
        AddString(command, "@TaxOffice", payload.TaxOffice, 120);
        AddString(command, "@TaxNumber", payload.TaxNumber, 64);
        AddDecimal(command, "@CreditLimit", payload.CreditLimit);
        command.Parameters.Add("@IsActive", SqlDbType.Bit).Value = payload.IsActive;
        AddString(command, "@Address", payload.Address, -1);
        AddString(command, "@Iban", payload.Iban, 64);
        AddString(command, "@ExportKey", payload.ExportKey ?? $"B2B-CUST-{payload.CustomerId}", 128);
        AddString(command, "@PayloadJson", JsonSerializer.Serialize(payload), -1);
        var externalRef = AddOutput(command, "@ExternalRef", 128);

        await command.ExecuteNonQueryAsync(cancellationToken);

        return NormalizeExternalRef(externalRef.Value) ?? payload.SourceReference ?? payload.ExportKey ?? $"B2B-CUST-{payload.CustomerId}";
    }

    private async Task<string> ExecuteCollectionProcedureAsync(
        string procedureName,
        CollectionWritePayload payload,
        CancellationToken cancellationToken)
    {
        EnsureStoredProcedureOptions(procedureName);

        await using var connection = new SqlConnection(_options.SqlConnectionString);
        await connection.OpenAsync(cancellationToken);

        await using var command = CreateCommand(connection, procedureName);
        AddString(command, "@CustomerExternalRef", payload.CustomerExternalRef, 128);
        AddString(command, "@CustomerCode", payload.CustomerCode, 64);
        AddDate(command, "@CollectionDate", payload.Date);
        AddString(command, "@Method", payload.Method, 32);
        AddDecimal(command, "@Amount", payload.Amount);
        AddString(command, "@Currency", payload.Currency, 3);
        AddString(command, "@ReferenceNo", payload.ReferenceNo, 120);
        AddString(command, "@Note", payload.Note, -1);
        AddString(command, "@CashboxCode", payload.CashboxCode, 64);
        AddString(command, "@ExportKey", payload.ExportKey ?? $"B2B-COL-{payload.CollectionId}", 128);
        AddString(command, "@PayloadJson", JsonSerializer.Serialize(payload), -1);
        var externalRef = AddOutput(command, "@ExternalRef", 128);

        await command.ExecuteNonQueryAsync(cancellationToken);

        return NormalizeExternalRef(externalRef.Value) ?? payload.ExportKey ?? $"B2B-COL-{payload.CollectionId}";
    }

    private void EnsureStoredProcedureOptions(string procedureName)
    {
        if (string.IsNullOrWhiteSpace(_options.SqlConnectionString))
        {
            throw new InvalidOperationException("Logo:SqlConnectionString bos. StoredProcedure modu icin SQL connection string gerekli.");
        }

        if (string.IsNullOrWhiteSpace(procedureName))
        {
            throw new InvalidOperationException("Logo write procedure adi bos. appsettings.json icinde ilgili prosedur adini verin.");
        }
    }

    private static SqlCommand CreateCommand(SqlConnection connection, string procedureName)
    {
        return new SqlCommand(procedureName, connection)
        {
            CommandType = CommandType.StoredProcedure,
            CommandTimeout = 120
        };
    }

    private static void AddString(SqlCommand command, string name, string? value, int size)
    {
        var parameter = command.Parameters.Add(name, SqlDbType.NVarChar, size);
        parameter.Value = string.IsNullOrWhiteSpace(value) ? DBNull.Value : value.Trim();
    }

    private static void AddDecimal(SqlCommand command, string name, string? value)
    {
        var parameter = command.Parameters.Add(name, SqlDbType.Decimal);
        parameter.Precision = 18;
        parameter.Scale = 2;

        if (decimal.TryParse(value, System.Globalization.NumberStyles.Number, System.Globalization.CultureInfo.InvariantCulture, out var parsed))
        {
            parameter.Value = parsed;
            return;
        }

        parameter.Value = DBNull.Value;
    }

    private static void AddDate(SqlCommand command, string name, string? value)
    {
        var parameter = command.Parameters.Add(name, SqlDbType.Date);
        if (DateTime.TryParse(value, System.Globalization.CultureInfo.InvariantCulture, System.Globalization.DateTimeStyles.AssumeLocal, out var parsed))
        {
            parameter.Value = parsed.Date;
            return;
        }

        parameter.Value = DBNull.Value;
    }

    private static SqlParameter AddOutput(SqlCommand command, string name, int size)
    {
        var parameter = command.Parameters.Add(name, SqlDbType.NVarChar, size);
        parameter.Direction = ParameterDirection.Output;

        return parameter;
    }

    private static string? NormalizeExternalRef(object value)
    {
        if (value is null or DBNull)
        {
            return null;
        }

        var normalized = Convert.ToString(value)?.Trim();
        return string.IsNullOrEmpty(normalized) ? null : normalized;
    }
}
