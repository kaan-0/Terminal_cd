using System.Windows;
using CDTerminal.Services;
using Microsoft.Extensions.DependencyInjection;

namespace CDTerminal;

public partial class MainWindow : Window
{
    private readonly ServiceProvider _serviceProvider;

    public MainWindow()
    {
        InitializeComponent();

        var services = new ServiceCollection();

        services.AddWpfBlazorWebView();

        services.AddSingleton<ISerialPortService, SerialPortService>();

        _serviceProvider = services.BuildServiceProvider();

        Resources.Add("services", _serviceProvider);
    }

    protected override void OnClosed(EventArgs e)
    {
        _serviceProvider.Dispose();

        base.OnClosed(e);
    }
}