using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using Unitec.ErpServer;

var builder = Host.CreateApplicationBuilder(args);
builder.Services.AddWindowsService(options =>
{
    options.ServiceName = Unitec.ErpCommon.ErpPaths.ServiceName;
});
builder.Services.AddHostedService<ErpServerWorker>();

var host = builder.Build();
await host.RunAsync();
