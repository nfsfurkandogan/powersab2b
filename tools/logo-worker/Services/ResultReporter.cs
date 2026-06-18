using System.Net.Http.Json;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;
using Powersa.LogoWorker.Configuration;
using Powersa.LogoWorker.Models;

namespace Powersa.LogoWorker.Services;

public sealed class ResultReporter
{
    private readonly HttpClient _httpClient;
    private readonly B2BApiOptions _options;
    private readonly ILogger<ResultReporter> _logger;

    public ResultReporter(HttpClient httpClient, IOptions<B2BApiOptions> options, ILogger<ResultReporter> logger)
    {
        _httpClient = httpClient;
        _options = options.Value;
        _logger = logger;
    }

    public async Task ReportCustomerAsync(AckRecord record, CancellationToken cancellationToken)
    {
        var request = new HttpRequestMessage(HttpMethod.Post, BuildUrl(_options.CustomerAckPath))
        {
            Content = JsonContent.Create(new
            {
                records = new[]
                {
                    new
                    {
                        customer_id = record.CustomerId,
                        status = record.Status,
                        external_ref = record.ExternalRef,
                        error = record.Error,
                        meta = record.Meta
                    }
                }
            })
        };

        request.Headers.Add("X-Integration-Key", _options.CustomerSyncKey);

        var response = await _httpClient.SendAsync(request, cancellationToken);
        response.EnsureSuccessStatusCode();
        _logger.LogInformation("Customer ack sent for customer_id={CustomerId} status={Status}", record.CustomerId, record.Status);
    }

    public async Task ReportCollectionAsync(AckRecord record, CancellationToken cancellationToken)
    {
        var request = new HttpRequestMessage(HttpMethod.Post, BuildUrl(_options.CollectionAckPath))
        {
            Content = JsonContent.Create(new
            {
                records = new[]
                {
                    new
                    {
                        collection_id = record.CollectionId,
                        status = record.Status,
                        external_ref = record.ExternalRef,
                        error = record.Error,
                        meta = record.Meta
                    }
                }
            })
        };

        request.Headers.Add("X-Integration-Key", _options.CollectionSyncKey);

        var response = await _httpClient.SendAsync(request, cancellationToken);
        response.EnsureSuccessStatusCode();
        _logger.LogInformation("Collection ack sent for collection_id={CollectionId} status={Status}", record.CollectionId, record.Status);
    }

    private Uri BuildUrl(string path)
    {
        return new Uri(new Uri(_options.BaseUrl.TrimEnd('/') + "/"), path.TrimStart('/'));
    }
}
