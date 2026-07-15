DEPENDENCIA OPCIONAL PARA INSTALACIÓN COMPLETAMENTE OFFLINE

CD Terminal usa Microsoft Edge WebView2 Runtime para mostrar la interfaz Blazor Hybrid.

Si deseas que el instalador funcione en una computadora sin internet y sin WebView2 instalado:

1. Descarga el instalador Evergreen Standalone x64 de Microsoft Edge WebView2 Runtime.
2. Renómbralo exactamente como:
   MicrosoftEdgeWebView2RuntimeInstallerX64.exe
3. Colócalo dentro de esta carpeta:
   Installer\Dependencies\
4. Ejecuta de nuevo Scripts\crear-instalador.ps1

El script de Inno Setup detectará WebView2 y solo ejecutará esta dependencia cuando haga falta.
