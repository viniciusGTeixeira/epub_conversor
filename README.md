# README - Conversor de Arquivos EPUB/PDF para EPUB 2

Seja bem vindo a documentaçao oficial do conversor :) 
Este projeto implementa um conversor de arquivos EPUB ou PDF para EPUB 2, com validação e reestruturação do conteúdo, utilizando Laravel 8. O objetivo é receber arquivos EPUB ou PDF, realizar a conversão necessária, reestruturar para o formato EPUB 2 e disponibilizar o arquivo reestruturado para download.

## Funcionalidades

- **Conversão de Arquivos:** Converte arquivos EPUB para EPUB 2 e PDF para XHTML e posteriormente para EPUB 2.
- **Validação:** Valida a estrutura e conteúdo dos arquivos EPUB.
- **Reestruturação:** Reorganiza o conteúdo do EPUB para o formato EPUB 2.
- **Download:** Disponibiliza o arquivo EPUB 2 reestruturado para download.

## Testando com Postman

1. **Endpoint:** Use o endpoint `POST /api/converter` para enviar arquivos EPUB ou PDF.
2. **Parâmetros:**
   - `file`: Arquivo EPUB ou PDF a ser enviado.
   - `token`: Token de autenticação (exemplo: `token-teste`).
3. **Resposta:** Recebe um URL público para download do arquivo EPUB 2 convertido.

## Instalação e Configuração

### Pré-requisitos

- PHP >= 7.4
- Composer
- Laravel

### Configuração Inicial

1. Clone o repositório.
2. Instale as dependências do Composer:


### Como funciona um arquivo EPUB?

O código-fonte segue o **Maven standard directory layout** clique neste link para conhecer melhor: https://maven.apache.org/guides/introduction/introduction-to-the-standard-directory-layout.html

em suma um arquivo epub 2 e epub 3 seguem:

src/test/resources
├── epub2
│   ├── container-epub.feature
│   ├── content-document-xhtml.feature
│   ├── navigation-ncx-document.feature
│   ├── package-document.feature
|   ├── …
|   └── files
│       ├── epub
│       ├── content-document-xhtml
│       ├── ncx-document
│       ├── package-document
│       └── …
└── epub3
    ├── core-container-epub.feature
    ├── core-content-document-xhtml.feature
    ├── core-navigation-document.feature
    ├── core-navigation-epub.feature
    ├── …
    └── files
        ├── epub
        ├── content-document-xhtml
        ├── navigation-document
        ├── package-document
        └── …

O que na pratica complica em muito para garantir a viabilidade das conversões pois como pode notar as arquiteturas são completamente diferentes o Epub 3 consegue trabalhar com metadados mais sofisticados enquanto o epub 2 trabalha como uma versao atualizada do epub 1 aceitando novos tipos de arquivos como css e alguns metadados nao suportados no epub 1. logo a conversao de apub 1 ou 2 para epub 3 seria muito mais simples pois nao haveriam arquivos complexos para quebra em suma seria apenas reservar o conteudo e estruturas bases estaticas para para as features complexas. 

**para entender melhor  a diferença**

Suporte a Multimídia e Interatividade:

EPUB 2: Oferece suporte limitado para recursos multimídia e interatividade. É mais focado em texto simples e imagens estáticas.
EPUB 3: Possui suporte avançado para áudio, vídeo, animações, scripts e outros elementos interativos. Isso permite experiências de leitura mais dinâmicas e envolventes.
Acessibilidade:

EPUB 2: Oferece suporte básico para acessibilidade, como texto alternativo para imagens.
EPUB 3: Introduziu melhorias significativas em acessibilidade, incluindo suporte a descrições textuais detalhadas para todos os elementos multimídia e suporte a tecnologias de assistência.
Layout e Design:

EPUB 2: Possui um modelo de layout fixo ou fluido, com opções limitadas para adaptação dinâmica ao tamanho da tela.
EPUB 3: Introduziu Layouts Adaptáveis, que permitem ajustar o conteúdo com base no dispositivo e nas preferências do usuário, proporcionando uma experiência de leitura mais personalizada.
Semântica e Semântica Estruturada:

EPUB 2: Usa um modelo de marcação mais simples e menos estruturado.
EPUB 3: Utiliza HTML5 e CSS3 para uma marcação mais rica e semântica, facilitando a indexação e a acessibilidade do conteúdo.
Globalização e Localização:

EPUB 2: Limitado em termos de suporte para múltiplos idiomas e layouts adaptados para diferentes regiões.
EPUB 3: Melhorou significativamente a capacidade de suportar diferentes idiomas, scripts e layouts adaptados culturalmente.


### Para Finalizar ###

**O que é de fato o desafio e como a api funciona?**

A api em si é um parser, o que é um parser?

o termo "parse" refere-se ao processo de analisar e interpretar dados ou conteúdos em um formato específico, como strings de texto, XML, JSON, entre outros formatos estruturados. O objetivo principal do parsing é extrair informações significativas ou transformar dados de um formato para outro de maneira legível e utilizável pelo programa.

exemplo em PHP:

` $xmlString = '<book><title>PHP Cookbook</title><author>O\'Reilly</author></book>';
$xml = simplexml_load_string($xmlString);
echo $xml->title; `


indico veementemente que estude as bases de estudo que usei para criar o conversor:

https://www.mobileread.com/forums/showthread.php?t=355435
https://www.w3.org/publishing/epubcheck/docs/test-suite/
https://manual.calibre-ebook.com/conversion.html
https://developers.zamzar.com/


### Como identificar com visao humana, qual a versao do epub em questao? ###

O principais componentes de um arquivo epub sao os arquivos content.opf e toc.ncx sendo eles responsáveis por direcionar o conteudo do epub, indicando os capitulos, titulos, páginas, versões e afins, sendo assim em um conversor é essencial que entendamos o core da aplicaçao que seria, se fato, criar corretamente os arquivos content.opf e toc.ncx.

vamos destrinchar esse conceito:

O formato OPF (Open Packaging Format) é um componente central da especificação EPUB (Electronic Publication) usada para e-books. Ele define a estrutura e organização de um arquivo EPUB, incluindo metadados, conteúdo e recursos necessários para a renderização do livro eletrônico.

Aqui estão os principais componentes de um arquivo OPF:

Metadados: Contém informações sobre o livro, como título, autor, idioma, data de publicação, entre outros.
Manifesto: Lista todos os arquivos que compõem o livro, como capítulos, imagens, folhas de estilo (CSS), etc.
Spine: Define a ordem de leitura dos arquivos listados no manifesto.
Guide: Opcionalmente, fornece referências a partes importantes do livro, como capa, índice, etc.

em um arquivo padrão será encontrado o seguinte conxteto, por exemplo:
`<?xml version="1.0" encoding="UTF-8"?><package xmlns="http://www.idpf.org/2007/opf" version="2.0">`

podemos ver alguns detalhes ai:

***xmlns="http://www.idpf.org/2007/opf"***
***version="2.0"***

sendo o primeiro parte do seguinte contexto:
As diferentes versões do OPF (Open Packaging Format) e EPUB (Electronic Publication) têm suas próprias especificações e melhorias ao longo do tempo:

http://www.idpf.org/2007/opf:

Esta versão refere-se ao formato OPF conforme definido pelo IDPF em 2007.
É utilizada principalmente para especificar a estrutura de arquivos e metadados de eBooks no formato EPUB.
Define como os arquivos devem ser organizados dentro do EPUB e quais informações devem ser incluídas nos metadados.
http://www.idpf.org/2003/opf:

Esta versão é uma versão anterior do OPF, definida pelo IDPF em 2003.
Apesar de menos comum hoje em dia, ainda pode ser encontrada em eBooks mais antigos que seguem as especificações da época.
http://www.idpf.org/epub/30:

Esta é a especificação do formato EPUB 3.0, que inclui melhorias significativas em relação às versões anteriores.
EPUB 3.0 introduziu suporte a recursos avançados como áudio, vídeo, conteúdo semântico estruturado (semelhante ao HTML5) e suporte para scripts e fontes.
É a versão mais recente e recomendada para criar eBooks modernos que aproveitam todas as funcionalidades mais recentes suportadas pelos leitores EPUB.
Em resumo, cada versão representa um estágio na evolução das especificações do formato EPUB e OPF, com a versão mais recente (EPUB 3.0) oferecendo recursos mais avançados e melhor suporte para conteúdo multimídia e interativo.

A seguinte dúvida pode surgir: 

***Entao os 3 sao epub 3.0?***

Não, os três links que você mencionou não são todos EPUB 3.0. Vamos esclarecer:

http://www.idpf.org/2007/opf: Este link refere-se ao formato OPF (Open Packaging Format) usado para estruturar metadados e conteúdos de eBooks. Embora não seja especificamente uma versão de EPUB, o OPF é crucial para a estruturação dos arquivos dentro do formato EPUB. A versão de 2007 do OPF está alinhada com as especificações gerais da época, que podem ser usadas em eBooks EPUB 2 ou EPUB 3, dependendo da implementação e das exigências do conteúdo.

http://www.idpf.org/2003/opf: Esta é uma versão mais antiga do OPF, definida em 2003. Ela também se aplica principalmente à organização de arquivos e metadados em eBooks, especialmente em versões mais antigas do formato EPUB, como EPUB 2.0.

http://www.idpf.org/epub/30: Esta é a especificação oficial do formato EPUB 3.0. Ela define as diretrizes para a criação de eBooks que suportam recursos avançados como áudio, vídeo, semântica estruturada, scripts e fontes. O EPUB 3.0 representa a versão mais recente e avançada do formato, projetada para oferecer uma experiência de leitura mais rica e interativa.

Portanto, enquanto os links 1 e 2 se referem ao OPF usado dentro dos eBooks, o terceiro link especifica a versão mais recente e avançada do formato EPUB, o EPUB 3.0.


ao clicar nos links voce pode acessar o conteudo especifico de cada formato.


Para mais informações sobre o desenvolvimento do EPUB, consulte Publishing@W3C. (https://www.w3.org/publishing/)

Para mais informações sobre namespaces XML, consulte Namespaces in XML. (https://www.w3.org/TR/REC-xml-names/)


