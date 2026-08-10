<?php

namespace Tests\Unit;

use App\Services\DocumentoConfiguracionPdf;
use PHPUnit\Framework\TestCase;

class DocumentoConfiguracionPdfTest extends TestCase
{
    public function test_genera_una_ficha_de_dos_paginas_solo_con_datos_del_panel(): void
    {
        $pdf = (new DocumentoConfiguracionPdf())->generar([
            'emitido_en' => '27/07/2026 09:15',
            'cliente' => [
                'codigo' => 'CLI0002',
                'nombre' => 'Cliente de prueba',
                'activo' => true,
            ],
            'equipo' => [
                'codigo' => 'LC0002C',
                'nombre' => 'Equipo principal',
                'ubicacion' => 'Tegucigalpa',
                'activo' => true,
                'token' => str_repeat('A', 64),
            ],
            'acceso' => [
                'nombre' => 'Usuario cliente',
                'email' => 'cliente@example.com',
                'password_temporal' => 'ClaveSegura123',
            ],
            'sensores' => [
                [
                    'ranura' => 1,
                    'nombre' => 'Sensor ambiental',
                    'tipo' => 'Temperatura y humedad',
                    'slave' => 13,
                    'funcion' => 3,
                    'registro_inicial' => 0,
                    'cantidad_registros' => 2,
                    'activo' => true,
                    'lecturas' => [
                        [
                            'indice' => 0,
                            'nombre' => 'Temperatura',
                            'unidad' => '°C',
                            'activo' => true,
                        ],
                        [
                            'indice' => 1,
                            'nombre' => 'Humedad',
                            'unidad' => '%',
                            'activo' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('/Count 2', $pdf);
        $this->assertStringContainsString('LC0002C', $pdf);
        $this->assertStringContainsString(str_repeat('A', 64), $pdf);
        $this->assertStringContainsString('Sensor ambiental', $pdf);
        $this->assertStringContainsString('Temperatura', $pdf);
        $this->assertStringNotContainsString('SETSENSOR', $pdf);
        $this->assertStringNotContainsString('/api/v1/mediciones', $pdf);
        $this->assertStringNotContainsString('192.168.1.2', $pdf);
        $this->assertStringEndsWith('%%EOF', $pdf);
    }
}
