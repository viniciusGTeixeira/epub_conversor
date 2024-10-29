<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

/**
 * Controller para conversão e validação de arquivos EPUB e PDF.
 */
class FileConversionController extends Controller
{
    protected $pdfToXhtmlConverter;

    public function __construct(PdfToXhtmlConverter $pdfToXhtmlConverter)
    {
        $this->pdfToXhtmlConverter = $pdfToXhtmlConverter;
    }

    /**
     * Converte um arquivo EPUB ou PDF, reestrutura e valida seu conteúdo para EPUB 2.
     *
     * @param Request $request Requisição contendo o arquivo EPUB ou PDF e token de autenticação.
     * @return \Illuminate\Http\JsonResponse Resposta JSON com o URL público do EPUB reestruturado.
     */
    public function convertFile(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file',
                'token' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()->first()], 400);
            }

            $token = $request->input('token');
            if ($token !== 'token-teste') {
                return response()->json(['error' => 'Token inválido'], 403);
            }

            $file = $request->file('file');
            $originalFileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $fileType = strtolower($file->getClientOriginalExtension());

            $tempDir = storage_path('app/public/temp_epub');

            if ($fileType === 'epub') {
                $epubPath = $file->getRealPath();
                $extractedDir = $this->extractEpub($epubPath);
                $validationResponse = $this->validateEpub($extractedDir);

                $restructuredEpubPath = $this->restructureEpubToEPUB2($extractedDir, $tempDir, $originalFileName);

                return response()->json([
                    'message' => 'Arquivo EPUB reestruturado com sucesso para EPUB 2!',
                    'file_url' => Storage::url(basename($restructuredEpubPath))
                ]);
            } elseif ($fileType === 'pdf') {
                $xhtmlContent = $this->pdfToXhtmlConverter->convert($file);
                $xhtmlPath = $tempDir . '/' . $originalFileName . '.xhtml';
                file_put_contents($xhtmlPath, $xhtmlContent);

                $restructuredEpubPath = $this->restructureEpubToEPUB2($xhtmlPath, $tempDir, $originalFileName);

                return response()->json([
                    'message' => 'Arquivo PDF convertido para EPUB 2 com sucesso!',
                    'file_url' => Storage::url(basename($restructuredEpubPath))
                ]);
            } else {
                return response()->json(['error' => 'Formato de arquivo não suportado.'], 400);
            }

        } catch (\Exception $e) {
            \Log::error('Erro ao converter arquivo: ' . $e->getMessage());
            return response()->json(['error' => 'Ocorreu um erro durante a conversão do arquivo.'], 500);
        }
    }


    /**
     * Extrai um arquivo EPUB para um diretório temporário.
     *
     * @param string $epubPath Caminho do arquivo EPUB a ser extraído.
     * @return string Diretório onde o EPUB foi extraído.
     * @throws \Exception Quando não é possível abrir o arquivo EPUB para extração.
     */
    private function extractEpub($epubPath)
    {
        $extractedDir = storage_path('app/public/extracted_epub');

        if (!is_dir($extractedDir)) {
            mkdir($extractedDir, 0777, true);
        }

        try {
            $zip = new ZipArchive();
            $openResult = $zip->open($epubPath);

            if ($openResult !== true) {
                throw new \Exception('Não foi possível abrir o arquivo EPUB para extração. Código de erro: ' . $openResult);
            }

            $zip->extractTo($extractedDir);
            $zip->close();

            $mimetypePath = $extractedDir . '/mimetype';
            if (!file_exists($mimetypePath) || trim(file_get_contents($mimetypePath)) !== 'application/epub+zip') {
                throw new \Exception('Arquivo mimetype ausente ou incorreto no EPUB.');
            }

            $containerPath = $extractedDir . '/META-INF/container.xml';
            if (!file_exists($containerPath)) {
                throw new \Exception('Arquivo container.xml ausente no EPUB.');
            }

            return $extractedDir;
        } catch (\Exception $e) {
            throw new \Exception('Erro ao extrair o arquivo EPUB: ' . $e->getMessage());
        }
    }

    /**
     * Valida a estrutura e conteúdo de um arquivo EPUB.
     *
     * @param string $epubPath Diretório onde o EPUB foi extraído.
     * @return array Array com informações sobre a validação (e.g., ['valid' => true]).
     *               Implemente a lógica de validação apropriada conforme os requisitos do EPUB.
     */
    private function validateEpub($epubPath)
    {
        return ['valid' => true];
    }

    /**
     * Reestrutura um arquivo EPUB para o formato EPUB 2.0.
     *
     * @param string $extractedDir Diretório onde o EPUB foi extraído inicialmente.
     * @param string $tempDir Diretório temporário para armazenar o EPUB reestruturado.
     * @return string Caminho completo para o arquivo EPUB reestruturado.
     * @throws \Exception Quando ocorre um erro ao reestruturar o arquivo EPUB.
     */
    private function restructureEpubToEPUB2($extractedDir, $tempDir, $originalFileName)
    {
        $pdfConvertedDir = $tempDir . '/pdf_converted';
        if (is_dir($pdfConvertedDir)) {
            return $this->generateEpubFromConvertedFiles($pdfConvertedDir, $tempDir, $originalFileName);
        }

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $oebpsDir = $tempDir . '/OEBPS';
        if (!is_dir($oebpsDir)) {
            mkdir($oebpsDir, 0777, true);
        }

        $restructuredEpubPath = $tempDir . '/' . $originalFileName . '_epub2.epub';

        $zip = new ZipArchive();
        if ($zip->open($restructuredEpubPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Não foi possível criar o arquivo EPUB 2.0 reestruturado.');
        }

        try {
            $zip->addFromString('mimetype', 'application/epub+zip');

            $containerPath = $extractedDir . '/META-INF/container.xml';
            $zip->addFile($containerPath, 'META-INF/container.xml');

            $oebpsDirExtracted = $extractedDir . '/OEBPS';
            if (is_dir($oebpsDirExtracted)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($oebpsDirExtracted),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );

                $manifestItems = [];
                $spineItems = [];

                foreach ($files as $name => $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($extractedDir) + 1);

                        if (pathinfo($filePath, PATHINFO_EXTENSION) === 'xhtml') {
                            $this->modifyXhtmlFile($filePath);
                        }

                        $zip->addFile($filePath, $relativePath);

                        if (pathinfo($filePath, PATHINFO_EXTENSION) === 'xhtml') {
                            $id = pathinfo($filePath, PATHINFO_FILENAME);
                            $manifestItems[] = [
                                'id' => $id,
                                'href' => $relativePath,
                                'media-type' => 'application/xhtml+xml'
                            ];
                            $spineItems[] = $id;
                        }
                    }
                }

                $this->createManifestAndSpine($tempDir, $manifestItems, $spineItems);
            }

            $zip->close();

            Storage::put('public/restructured_epubs/' . basename($restructuredEpubPath), file_get_contents($restructuredEpubPath));

            return 'storage/restructured_epubs/' . basename($restructuredEpubPath);
        } catch (\Exception $e) {
            $zip->close();
            if (file_exists($restructuredEpubPath)) {
                unlink($restructuredEpubPath);
            }
            throw new \Exception('Erro ao reestruturar o arquivo EPUB 2.0: ' . $e->getMessage());
        }
    }

    private function generateEpubFromConvertedFiles($pdfConvertedDir, $tempDir, $originalFileName)
    {
        $restructuredEpubPath = $tempDir . '/' . $originalFileName . '_epub2.epub';

        $zip = new ZipArchive();
        if ($zip->open($restructuredEpubPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Não foi possível criar o arquivo EPUB 2.0 reestruturado a partir de converted_pdf.');
        }

        try {
            $zip->addFromString('mimetype', 'application/epub+zip');

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($pdfConvertedDir),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($pdfConvertedDir) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }

            $zip->close();

            Storage::put('public/restructured_epubs/' . basename($restructuredEpubPath), file_get_contents($restructuredEpubPath));

            return 'storage/restructured_epubs/' . basename($restructuredEpubPath);
        } catch (\Exception $e) {
            $zip->close();
            if (file_exists($restructuredEpubPath)) {
                unlink($restructuredEpubPath);
            }
            throw new \Exception('Erro ao criar o arquivo EPUB 2.0 a partir de converted_pdf: ' . $e->getMessage());
        }
    }


    /**
     * Modifica um arquivo XHTML removendo elementos indesejados.
     *
     * @param string $filePath Caminho do arquivo XHTML a ser modificado.
     * @return void
     */
    private function modifyXhtmlFile($filePath)
    {
        $content = file_get_contents($filePath);

        $content = preg_replace('/<prev[^>]*>.*?<\/prev>/is', '', $content);
        $content = preg_replace('/<next[^>]*>.*?<\/next>/is', '', $content);

        file_put_contents($filePath, $content);
    }

    /**
     * Cria os arquivos de manifest e spine no diretório EPUB reestruturado.
     *
     * @param string $tempDir Diretório temporário para armazenar o EPUB reestruturado.
     * @param array $manifestItems Array de itens para o manifest.
     * @param array $spineItems Array de itens para o spine.
     * @return void
     */
    private function createManifestAndSpine($tempDir, $manifestItems, $spineItems)
    {
        $manifestPath = $tempDir . '/OEBPS/content.opf';
        $spinePath = $tempDir . '/OEBPS/toc.ncx';

        $manifestContent = '<?xml version="1.0" encoding="UTF-8"?>';
        $manifestContent .= '<package xmlns="http://www.idpf.org/2007/opf" version="2.0">';
        $manifestContent .= '<manifest>';

        foreach ($manifestItems as $item) {
            $manifestContent .= '<item id="' . $item['id'] . '" href="' . $item['href'] . '" media-type="' . $item['media-type'] . '"/>';
        }

        $manifestContent .= '</manifest>';
        $manifestContent .= '<spine toc="ncx">';

        foreach ($spineItems as $item) {
            $manifestContent .= '<itemref idref="' . $item . '"/>';
        }

        $manifestContent .= '</spine>';
        $manifestContent .= '</package>';

        file_put_contents($manifestPath, $manifestContent);

        $spineContent = '<?xml version="1.0" encoding="UTF-8"?>';
        $spineContent .= '<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">';
        $spineContent .= '<docTitle><text>Title</text></docTitle>';
        $spineContent .= '<navMap>';

        foreach ($spineItems as $index => $item) {
            $spineContent .= '<navPoint id="navPoint-' . ($index + 1) . '" playOrder="' . ($index + 1) . '">';
            $spineContent .= '<navLabel><text>' . $item . '</text></navLabel>';
            $spineContent .= '<content src="' . $manifestItems[$index]['href'] . '"/>';
            $spineContent .= '</navPoint>';
        }

        $spineContent .= '</navMap>';
        $spineContent .= '</ncx>';

        file_put_contents($spinePath, $spineContent);
    }

    /**
     * Converte um PDF enviado via upload para XHTML.
     *
     * @param Request $request
     * @return string Conteúdo XHTML gerado a partir do PDF.
     */
    public function convertPdfToXhtml(Request $request)
    {
        $pdfFile = $request->file('pdf');

        $converter = new PdfToXhtmlConverter();
        $xhtmlContent = $converter->convert($pdfFile);

        return $xhtmlContent;
    }
}
