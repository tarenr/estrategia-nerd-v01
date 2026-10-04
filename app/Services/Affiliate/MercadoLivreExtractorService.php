<?php
declare(strict_types=1);

namespace App\Services\Affiliate;

use Throwable;

final class MercadoLivreExtractorService
{
    private const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';

    public function __construct(
        private ?string $geminiApiKey = null,
        private string $publicPath = 'public',
    ) {
        if ($this->geminiApiKey === null && isset($_ENV['GEMINI_API_KEY'])) {
            $this->geminiApiKey = (string) $_ENV['GEMINI_API_KEY'];
        }
    }

    /**
     * Resolve a URL final seguindo redirects (necessario para meli.la).
     */
    public function resolveFinalUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = 'https://' . $url;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return $url;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 7,
            CURLOPT_USERAGENT => self::DEFAULT_USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
            ],
        ]);

        curl_exec($ch);
        $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        return $finalUrl !== '' ? $finalUrl : $url;
    }

    /**
     * Baixa o HTML da pagina com headers confiaveis.
     */
    public function fetchPage(string $url): ?string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 7,
            CURLOPT_USERAGENT => self::DEFAULT_USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
                'Cache-Control: no-cache',
            ],
        ]);

        $html = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($html === false || $httpCode < 200 || $httpCode >= 400) {
            return null;
        }

        return (string) $html;
    }

    /**
     * Extrai os metadados de produto da URL informada.
     * @return array<string, mixed>|null
     */
    public function extractProduct(string $url): ?array
    {
        $finalUrl = $this->resolveFinalUrl($url);
        $html = $this->fetchPage($finalUrl);
        if ($html === null || $html === '') {
            return null;
        }

        $data = [
            'original_url' => $url,
            'final_url' => $finalUrl,
            'titulo' => '',
            'preco' => 0.0,
            'preco_original' => null,
            'desconto_percentual' => null,
            'rating' => null,
            'rating_count' => null,
            'vendas_texto' => null,
            'selos' => [],
            'imagem_url' => null,
            'descricao' => '',
            'prompt_mestre' => '',
        ];

        // 1. Tenta extrair dados estruturados JSON-LD (schema.org/Product)
        $this->extractJsonLd($html, $data);

        // 2. Fallbacks em OpenGraph e Meta Tags
        if ($data['titulo'] === '') {
            if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i', $html, $m)) {
                $data['titulo'] = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (preg_match('/<title>(.*?)<\/title>/i', $html, $m)) {
                $data['titulo'] = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        if ($data['imagem_url'] === null || $data['imagem_url'] === '') {
            if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']/i', $html, $m)) {
                $data['imagem_url'] = trim($m[1]);
            }
        }

        // 3. Fallbacks de Preco via HTML do Mercado Livre
        if ($data['preco'] <= 0.0) {
            if (preg_match('/class=["\'][^"\']*andes-money-amount__fraction[^"\']*["\']>([\d.,]+)<\/span>/i', $html, $m)) {
                $rawFraction = str_replace('.', '', $m[1]);
                $data['preco'] = (float) str_replace(',', '.', $rawFraction);
            } elseif (preg_match('/["\']price["\']\s*:\s*["\']?([\d.]+)["\']?/i', $html, $m)) {
                $data['preco'] = (float) $m[1];
            } elseif (preg_match('/R\$\s*([\d.]+,\d{2})/i', $html, $m)) {
                $raw = str_replace('.', '', $m[1]);
                $data['preco'] = (float) str_replace(',', '.', $raw);
            }
        }

        // 4. Rating / Avaliacao média
        if ($data['rating'] === null) {
            if (preg_match('/class=["\'][^"\']*ui-review-capability__rating__average[^"\']*["\']>([\d.,]+)<\/span>/i', $html, $m)) {
                $data['rating'] = (float) str_replace(',', '.', $m[1]);
            } elseif (preg_match('/([\d.,]+)\s+de\s+5\s+estrelas/i', $html, $m)) {
                $data['rating'] = (float) str_replace(',', '.', $m[1]);
            }
        }

        if ($data['rating_count'] === null) {
            if (preg_match('/class=["\'][^"\']*ui-review-capability__rating__label[^"\']*["\']>(\(?\s*[\d.]+\s*.*?\)?)/i', $html, $m)) {
                $data['rating_count'] = (int) preg_replace('/[^\d]/', '', $m[1]);
            }
        }

        // 5. Volume de Vendas
        if (preg_match('/(\+?[\d.]+\s*mil?\s*vendidos)/i', $html, $m)) {
            $data['vendas_texto'] = trim($m[1]);
        }

        // 6. Selos (Mais Vendido, Loja Oficial, Full)
        $selos = [];
        if (stripos($html, 'mais vendido') !== false) {
            $selos[] = 'Mais Vendido';
        }
        if (stripos($html, 'loja oficial') !== false) {
            $selos[] = 'Loja Oficial';
        }
        if (stripos($html, 'mercadolider') !== false) {
            $selos[] = 'MercadoLíder Platinum';
        }
        if (stripos($html, 'ui-pdp-icon--full') !== false || stripos($html, 'chegará grátis') !== false) {
            $selos[] = 'Entrega Full';
        }
        $data['selos'] = array_values(array_unique($selos));

        // 7. Limpeza e Titulo Amigavel
        $cleanTitle = preg_replace('/\s*\|\s*Mercado\s*Livre.*$/i', '', (string) $data['titulo']);
        $cleanTitle = preg_replace('/\s*-\s*R\$.*$/i', '', (string) $cleanTitle);
        $data['titulo'] = trim((string) $cleanTitle);

        // 8. Calculo de desconto percentual se houver preco original
        if (isset($data['preco_original']) && (float) $data['preco_original'] > (float) $data['preco'] && (float) $data['preco'] > 0) {
            $pct = round((((float) $data['preco_original'] - (float) $data['preco']) / (float) $data['preco_original']) * 100);
            if ($pct > 0) {
                $data['desconto_percentual'] = $pct . '% OFF';
            }
        }

        // 9. Gera o Prompt Mestre para Imagem Padronizada
        $data['prompt_mestre'] = $this->generateImagePrompt($data['titulo'], $data['selos']);

        return $data;
    }

    /**
     * Gera o prompt mestre padronizado para IA no estilo Cyberpunk Hero Shot.
     * @param array<string> $highlights
     */
    public function generateImagePrompt(string $productTitle, array $highlights = []): string
    {
        $highlightStr = $highlights !== [] ? 'featuring ' . implode(', ', $highlights) . '.' : '';

        return sprintf(
            'Close-up hero commercial product photography of %s, occupying 75%% of the frame. Boldly centered foreground at a dynamic 3/4 angle, %s Ultra-clean minimalist dark tech studio setting, smooth polished dark reflective podium with subtle, clean neon cyan and orange circuit accents glowing beneath the glass. Shallow depth of field with soft bokeh background, zero clutter, zero machinery. Dramatic rim lighting highlighting the sleek edges, high-end advertising style, maximum product focus, ultra sharp detail, 8k resolution, 1:1 square aspect ratio.',
            $productTitle,
            $highlightStr
        );
    }

    /**
     * Pipeline de Padronizacao Grafica:
     * - Carrega imagem local ou de URL;
     * - Recorta para quadrado 1:1 centralizado;
     * - Redimensiona para exatos $targetSize x $targetSize (padrao 1080x1080);
     * - Converte para WebP (85% qualidade) com peso ultraleve (~80-130 KB);
     * - Salva no caminho relativo dentro de public/uploads/...
     *
     * @return array<string, mixed>
     */
    public function standardizeImage(
        string $sourceImagePathOrUrl,
        string $targetRelativePath,
        int $targetSize = 1080,
        int $quality = 85
    ): array {
        $sourceData = null;
        if (str_starts_with($sourceImagePathOrUrl, 'http://') || str_starts_with($sourceImagePathOrUrl, 'https://')) {
            $sourceData = @file_get_contents($sourceImagePathOrUrl);
        } elseif (is_file($sourceImagePathOrUrl)) {
            $sourceData = @file_get_contents($sourceImagePathOrUrl);
        }

        if ($sourceData === false || $sourceData === null || $sourceData === '') {
            return ['ok' => false, 'error' => 'Nao foi possivel carregar a imagem de origem.'];
        }

        $sourceImg = @imagecreatefromstring($sourceData);
        if ($sourceImg === false) {
            return ['ok' => false, 'error' => 'Formato de imagem de origem invalido.'];
        }

        $origW = imagesx($sourceImg);
        $origH = imagesy($sourceImg);

        // Determina o quadrado centralizado
        $cropSize = min($origW, $origH);
        $cropX = (int) floor(($origW - $cropSize) / 2);
        $cropY = (int) floor(($origH - $cropSize) / 2);

        $canvas = imagecreatetruecolor($targetSize, $targetSize);
        if ($canvas === false) {
            imagedestroy($sourceImg);
            return ['ok' => false, 'error' => 'Falha ao alocar canvas GD.'];
        }

        // Habilita canal alpha para caso tenha transparencia
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        imagecopyresampled(
            $canvas,
            $sourceImg,
            0,
            0,
            $cropX,
            $cropY,
            $targetSize,
            $targetSize,
            $cropSize,
            $cropSize
        );
        imagedestroy($sourceImg);

        $targetRelativePath = ltrim(str_replace('\\', '/', $targetRelativePath), '/');
        // Garante extensao .webp
        $targetRelativePath = (string) preg_replace('/\.(png|jpe?g|gif)$/i', '.webp', $targetRelativePath);
        if (!str_ends_with($targetRelativePath, '.webp')) {
            $targetRelativePath .= '.webp';
        }

        $absoluteTarget = rtrim($this->publicPath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $targetRelativePath);
        $dir = dirname($absoluteTarget);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            imagedestroy($canvas);
            return ['ok' => false, 'error' => 'Nao foi possivel criar o diretorio de destino: ' . $dir];
        }

        $saved = imagewebp($canvas, $absoluteTarget, $quality);
        imagedestroy($canvas);

        if (!$saved || !is_file($absoluteTarget)) {
            return ['ok' => false, 'error' => 'Falha ao salvar arquivo WebP em: ' . $absoluteTarget];
        }

        $fileSize = (int) filesize($absoluteTarget);

        return [
            'ok' => true,
            'relative_path' => $targetRelativePath,
            'absolute_path' => $absoluteTarget,
            'size' => $targetSize,
            'bytes' => $fileSize,
            'kb' => round($fileSize / 1024, 1),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractJsonLd(string $html, array &$data): void
    {
        if (preg_match_all('/<script\s+type=["\']application\/ld\+json["\']>(.*?)<\/script>/is', $html, $matches)) {
            foreach ($matches[1] as $jsonStr) {
                try {
                    $json = json_decode(trim($jsonStr), true);
                    if (!is_array($json)) {
                        continue;
                    }

                    // Pode ser array de objetos ou objeto direto
                    $items = isset($json['@type']) ? [$json] : $json;
                    foreach ($items as $item) {
                        if (!is_array($item)) {
                            continue;
                        }

                        $type = (string) ($item['@type'] ?? '');
                        if (strcasecmp($type, 'Product') === 0) {
                            if (isset($item['name']) && $data['titulo'] === '') {
                                $data['titulo'] = (string) $item['name'];
                            }
                            if (isset($item['image'])) {
                                $data['imagem_url'] = is_array($item['image']) ? (string) ($item['image'][0] ?? '') : (string) $item['image'];
                            }
                            if (isset($item['description']) && $data['descricao'] === '') {
                                $data['descricao'] = (string) $item['description'];
                            }
                            if (isset($item['offers']) && is_array($item['offers'])) {
                                $offers = isset($item['offers']['price']) ? $item['offers'] : ($item['offers'][0] ?? []);
                                if (isset($offers['price'])) {
                                    $data['preco'] = (float) $offers['price'];
                                }
                            }
                            if (isset($item['aggregateRating']) && is_array($item['aggregateRating'])) {
                                if (isset($item['aggregateRating']['ratingValue'])) {
                                    $data['rating'] = (float) $item['aggregateRating']['ratingValue'];
                                }
                                if (isset($item['aggregateRating']['reviewCount'])) {
                                    $data['rating_count'] = (int) $item['aggregateRating']['reviewCount'];
                                }
                            }
                        }
                    }
                } catch (Throwable) {
                    // Ignora JSON-LD malformado e segue
                }
            }
        }
    }
}
