<?php

namespace App\Http\Controllers;

use Smalot\PdfParser\Parser;
use Illuminate\Http\UploadedFile;

class PdfToXhtmlConverter
{
    /**
     * Converte um arquivo PDF para XHTML.
     *
     * @param UploadedFile $pdfFile Arquivo PDF a ser convertido.
     * @return string Conteúdo XHTML gerado.
     * @throws \Exception Quando não é possível converter o arquivo PDF.
     */
    public function convert(UploadedFile $pdfFile)
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($pdfFile->getRealPath());
        $numPages = count($pdf->getPages());

        $xhtmlContent = '<html><body>';

        for ($pageNumber = 1; $pageNumber <= $numPages; $pageNumber++) {
            $page = $pdf->getPages()[$pageNumber - 1];
            $text = $page->getText();

            $xhtmlContent .= $this->textToXhtml($text);
        }

        $xhtmlContent .= '</body></html>';

        $outputPath = 'public/temp_epub/output.xhtml';
        $directory = dirname(storage_path('app/' . $outputPath));

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(storage_path('app/' . $outputPath), $xhtmlContent);

        return $xhtmlContent;
    }

    /**
     * Converte texto para XHTML.
     *
     * @param string $text Texto a ser convertido.
     * @return string Texto convertido para XHTML.
     */
    protected function textToXhtml($text)
    {
        $lines = explode("\n", $text);
        $xhtmlContent = '';

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if (empty($trimmedLine)) {
                continue;
            }

            if ($this->isHeading1($trimmedLine)) {
                $xhtmlContent .= '<h1>' . htmlspecialchars($trimmedLine) . '</h1>';
            } elseif ($this->isHeading2($trimmedLine)) {
                $xhtmlContent .= '<h2>' . htmlspecialchars($trimmedLine) . '</h2>';
            } else {
                $xhtmlContent .= '<p>' . htmlspecialchars($trimmedLine) . '</p>';
            }
        }

        return $xhtmlContent;
    }

    /**
     * Verifica se uma linha é um cabeçalho de nível 1 (h1).
     *
     * @param string $line Linha de texto a ser verificada.
     * @return bool Verdadeiro se for um h1, falso caso contrário.
     * @description Heurística: verifica se a linha está em maiúsculas e tem menos de 60 caracteres
     */
    protected function isHeading1($line)
    {

        return strtoupper($line) === $line && strlen($line) <= 60;
    }

    /**
     * Verifica se uma linha é um cabeçalho de nível 2 (h2).
     *
     * @param string $line Linha de texto a ser verificada.
     * @return bool Verdadeiro se for um h2, falso caso contrário.
     * @Description Heurística: verifica se a linha começa com um número seguido de um ponto ou é uma frase capitalizada com menos de 60 caracteres
     */
    protected function isHeading2($line)
    {
        return preg_match('/^\d+\./', $line) || (ucfirst($line) === $line && strlen($line) <= 60);
    }
}
