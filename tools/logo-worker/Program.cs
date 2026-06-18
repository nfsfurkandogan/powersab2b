using Powersa.LogoWorker.Configuration;
using Powersa.LogoWorker.Services;

var builder = Host.CreateApplicationBuilder(args);

builder.Services.Configure<RabbitMqOptions>(builder.Configuration.GetSection("RabbitMq"));
builder.Services.Configure<B2BApiOptions>(builder.Configuration.GetSection("B2B"));
builder.Services.Configure<LogoOptions>(builder.Configuration.GetSection("Logo"));
builder.Services.Configure<WorkerOptions>(builder.Configuration.GetSection("Worker"));

builder.Services.AddHttpClient<ResultReporter>();
builder.Services.AddSingleton<LogoObjectsGateway>();
builder.Services.AddSingleton<LogoEventDispatcher>();
builder.Services.AddHostedService<RabbitMqConsumerService>();

var host = builder.Build();
host.Run();
