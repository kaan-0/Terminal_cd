CD Terminal - Terminales múltiples

Reemplaza:
1. Inicio.razor
2. Inicio.razor.css
3. MainWindow.xaml.cs

No modifiques:
- ISerialPortService.cs
- SerialPortService.cs
- MainWindow.xaml
- SelectorPersonalizado.razor

Cambio esencial:
ISerialPortService se registra como Transient para que cada pestaña tenga su propia instancia de SerialPort.

Después ejecuta:
dotnet clean
dotnet restore
dotnet build .\CDTerminal.csproj -c Debug

Comportamiento:
- El botón + crea Terminal 2, Terminal 3, etc.
- Cada pestaña conserva puerto, configuración, conexión, RX/TX y texto pendiente.
- Varias pestañas pueden conectarse simultáneamente, pero no al mismo puerto COM.
- X cierra una pestaña y libera su puerto.
- Siempre permanece al menos una terminal abierta.