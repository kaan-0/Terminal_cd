using System;
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
        services.AddTransient<ISerialPortService, SerialPortService>();
        services.AddSingleton<IFileExportService, FileExportService>();
        services.AddSingleton<IPerfilDispositivoService, PerfilDispositivoService>();

        _serviceProvider = services.BuildServiceProvider();

        Resources.Add("services", _serviceProvider);
    }

    private void MinimizeButton_Click(
        object sender,
        RoutedEventArgs e)
    {
        WindowState = WindowState.Minimized;
    }

    private void MaximizeRestoreButton_Click(
        object sender,
        RoutedEventArgs e)
    {
        WindowState = WindowState == WindowState.Maximized
            ? WindowState.Normal
            : WindowState.Maximized;
    }

    private void CloseButton_Click(
        object sender,
        RoutedEventArgs e)
    {
        Close();
    }

    private void Window_StateChanged(
        object? sender,
        EventArgs e)
    {
        if (WindowState == WindowState.Maximized)
        {
            MaximizeRestoreIcon.Text = "\uE923";
            MaximizeRestoreButton.ToolTip = "Restaurar";
        }
        else
        {
            MaximizeRestoreIcon.Text = "\uE922";
            MaximizeRestoreButton.ToolTip = "Maximizar";
        }
    }

    protected override void OnClosed(EventArgs e)
    {
        _serviceProvider.Dispose();
        base.OnClosed(e);
    }
}