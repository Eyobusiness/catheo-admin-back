<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /**
     * Génère un fichier CSV / Excel téléchargeable à partir d'un jeu de données.
     */
    public function exportCsv(string $filename, array $headers, array $rows): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM pour la prise en charge des caractères accentués dans Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    /**
     * Génère un document imprimable/PDF (HTML optimisé avec styles d'impression).
     */
    public function exportPdfHtml(string $titre, array $headers, array $rows, string $paroisseNom = 'Catheo'): StreamedResponse
    {
        $html = "
        <!DOCTYPE html>
        <html lang=\"fr\">
        <head>
            <meta charset=\"UTF-8\">
            <title>{$titre}</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; color: #333; margin: 20px; }
                .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #2563eb; padding-bottom: 10px; }
                .header h1 { margin: 0; color: #1e3a8a; font-size: 20px; }
                .header p { margin: 5px 0 0 0; color: #64748b; font-size: 13px; }
                table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                th { background-color: #f1f5f9; color: #1e293b; font-weight: bold; text-align: left; padding: 8px; border: 1px solid #cbd5e1; }
                td { padding: 8px; border: 1px solid #e2e8f0; text-align: left; }
                tr:nth-child(even) { background-color: #f8fafc; }
                .footer { margin-top: 30px; font-size: 10px; text-align: right; color: #94a3b8; }
            </style>
        </head>
        <body>
            <div class=\"header\">
                <h1>{$paroisseNom}</h1>
                <p>{$titre} - Généré le " . date('d/m/Y H:i') . "</p>
            </div>
            <table>
                <thead>
                    <tr>";

        foreach ($headers as $h) {
            $html .= "<th>{$h}</th>";
        }

        $html .= "</tr>
                </thead>
                <tbody>";

        foreach ($rows as $r) {
            $html .= "<tr>";
            foreach ($r as $cell) {
                $html .= "<td>" . htmlspecialchars((string)$cell) . "</td>";
            }
            $html .= "</tr>";
        }

        $html .= "
                </tbody>
            </table>
            <div class=\"footer\">Document extrait depuis l'application Catheo</div>
        </body>
        </html>";

        $response = new StreamedResponse(function () use ($html) {
            echo $html;
        });

        $filename = strtolower(str_replace([' ', '\''], ['_', ''], $titre)) . '.pdf';

        $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'inline; filename="' . $filename . '"');

        return $response;
    }
}
