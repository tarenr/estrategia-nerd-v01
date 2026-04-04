<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Support\View;

final class PagesController
{
    public function privacy(): void
    {
        View::render('site/page', [
            'title' => 'Politica de Privacidade | ' . (string) portal_config('nome_site', 'Estrategia Nerd'),
            'site_chrome' => false,
            'site_meta' => $this->siteMeta(),
            'page_title' => 'Politica de Privacidade',
            'page_intro' => 'Como tratamos informacoes, navegacao e inscricoes dentro do portal.',
            'page_sections' => [
                [
                    'title' => 'Dados coletados',
                    'body' => 'Podemos receber dados informados por voce em formularios, como nome e e-mail, alem de dados tecnicos basicos de navegacao necessarios para o funcionamento do portal.',
                ],
                [
                    'title' => 'Uso das informacoes',
                    'body' => 'As informacoes sao utilizadas para operar o portal, responder contatos, registrar inscricoes em newsletter e melhorar a experiencia do usuario.',
                ],
                [
                    'title' => 'Newsletter e cancelamento',
                    'body' => 'Inscricoes em newsletter podem ser interrompidas a qualquer momento. O objetivo do portal e enviar conteudo relevante, sem expor sua base de contatos.',
                ],
                [
                    'title' => 'Contato',
                    'body' => 'Para duvidas sobre privacidade, utilize o canal de contato informado no rodape do site.',
                ],
            ],
        ]);
    }

    public function terms(): void
    {
        View::render('site/page', [
            'title' => 'Termos de Uso | ' . (string) portal_config('nome_site', 'Estrategia Nerd'),
            'site_chrome' => false,
            'site_meta' => $this->siteMeta(),
            'page_title' => 'Termos de Uso',
            'page_intro' => 'Regras gerais de uso do portal, do conteudo editorial e das funcionalidades disponiveis.',
            'page_sections' => [
                [
                    'title' => 'Uso do conteudo',
                    'body' => 'O conteudo do portal e oferecido para leitura, consulta e acompanhamento editorial. Recomendacoes, reviews e opinioes sao produzidas com criterio, mas nao substituem verificacao propria do usuario.',
                ],
                [
                    'title' => 'Links e recomendacoes',
                    'body' => 'Algumas indicacoes podem levar para parceiros, plataformas externas ou campanhas. O usuario e sempre livre para decidir se deseja prosseguir.',
                ],
                [
                    'title' => 'Disponibilidade',
                    'body' => 'O portal pode atualizar, remover ou reorganizar conteudo e funcionalidades conforme a evolucao do projeto.',
                ],
                [
                    'title' => 'Boa utilizacao',
                    'body' => 'Nao e permitido utilizar formularios, comentarios ou recursos do site para envio abusivo, spam ou qualquer uso que prejudique o portal.',
                ],
            ],
        ]);
    }

    private function siteMeta(): array
    {
        return [
            'name' => (string) portal_config('nome_site', 'Estrategia Nerd'),
            'description' => (string) portal_config('descricao_site', 'Conteudo, tecnologia, cultura geek e oportunidades em um so lugar.'),
            'kicker' => (string) portal_config('site_kicker', 'Portal geek estrategico'),
            'footer' => (string) portal_config('footer_texto', 'Estrategia Nerd - Conteudo, links e ofertas geek'),
            'email' => (string) portal_config('email_contato', ''),
            'instagram' => (string) portal_config('instagram_url', ''),
            'tiktok' => (string) portal_config('tiktok_url', ''),
            'kwai' => (string) portal_config('kwai_url', ''),
            'youtube' => (string) portal_config('youtube_url', ''),
            'telegram' => (string) portal_config('telegram_url', ''),
            'whatsapp' => (string) portal_config('whatsapp_url', ''),
            'brand_symbol' => $this->toPublicUrl((string) portal_config('brand_symbol_url', '')),
        ];
    }

    private function toPublicUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return preg_match('~^https?://~i', $value) ? $value : url('/' . ltrim($value, '/'));
    }
}
