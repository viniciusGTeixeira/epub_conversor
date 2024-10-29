<?php

namespace App\Http\Controllers;

use Smalot\PdfParser\Parser;
use Illuminate\Http\UploadedFile;

class PdfToXhtmlConverter
{
    /**
     * Converte um arquivo PDF para XHTML e gera a estrutura EPUB 2.
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

        $outputDir = storage_path('app/public/pdf_converted');
        $outputPath = $outputDir . '/output.xhtml';

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        file_put_contents($outputPath, $xhtmlContent);

        $this->generateEpub2Structure($outputDir, $xhtmlContent);

        return $xhtmlContent;
    }

    /**
     * Gera a estrutura EPUB 2.
     *
     * @param string $outputDir Diretório onde a estrutura EPUB será gerada.
     * @param string $xhtmlContent Conteúdo XHTML do livro.
     */
    protected function generateEpub2Structure($outputDir, $xhtmlContent)
    {
        file_put_contents($outputDir . '/mimetype', 'application/epub+zip');

        $metaInfDir = $outputDir . '/META-INF';
        if (!is_dir($metaInfDir)) {
            mkdir($metaInfDir, 0777, true);
        }

        $containerXml = '<?xml version="1.0" encoding="UTF-8" ?>
        <container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
            <rootfiles>
                <rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/>
            </rootfiles>
        </container>';
        file_put_contents($metaInfDir . '/container.xml', $containerXml);

        $oebpsDir = $outputDir . '/OEBPS';
        if (!is_dir($oebpsDir)) {
            mkdir($oebpsDir, 0777, true);
        }

        $contentOpf = '<?xml version="1.0" encoding="UTF-8" ?>
        <package version="2.0" xmlns="http://www.idpf.org/2007/opf" unique-identifier="BookId">
            <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
                <dc:title>PDF to EPUB</dc:title>
                <dc:language>en</dc:language>
                <dc:identifier id="BookId">urn:uuid:' . uniqid() . '</dc:identifier>
            </metadata>
            <manifest>
                <item id="content" href="output.xhtml" media-type="application/xhtml+xml"/>
                <item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
            </manifest>
            <spine toc="ncx">
                <itemref idref="content"/>
            </spine>
        </package>';
        file_put_contents($oebpsDir . '/content.opf', $contentOpf);

        $tocNcx = '<?xml version="1.0" encoding="UTF-8" ?>
        <ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
            <head>
                <meta name="dtb:uid" content="urn:uuid:' . uniqid() . '"/>
                <meta name="dtb:depth" content="1"/>
                <meta name="dtb:totalPageCount" content="0"/>
                <meta name="dtb:maxPageNumber" content="0"/>
            </head>
            <docTitle>
                <text>PDF to EPUB</text>
            </docTitle>
            <navMap>
                <navPoint id="navPoint-1" playOrder="1">
                    <navLabel>
                        <text>Start</text>
                    </navLabel>
                    <content src="output.xhtml"/>
                </navPoint>
            </navMap>
        </ncx>';
        file_put_contents($oebpsDir . '/toc.ncx', $tocNcx);

        rename($outputDir . '/output.xhtml', $oebpsDir . '/output.xhtml');
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
