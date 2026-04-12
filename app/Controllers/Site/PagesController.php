<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Support\View;

final class PagesController
{
    public function privacy(): void
    {
        View::render('site/page', [
            'title' => 'Política de Privacidade | ' . (string) portal_config('nome_site', 'Estratégia Nerd'),
            'site_chrome' => false,
            'site_meta' => $this->siteMeta(),
            'page_title' => 'Política de Privacidade',
            'page_intro' => 'Como tratamos informações, navegação e inscrições dentro do portal.',
            'page_sections' => [
                [
                    'title' => 'Dados coletados',
                    'body' => 'Podemos receber dados informados por você em formulários, como nome e e-mail, além de dados técnicos básicos de navegação necessários para o funcionamento do portal.',
                ],
                [
                    'title' => 'Uso das informações',
                    'body' => 'As informações são utilizadas para operar o portal, responder contatos, registrar inscrições em newsletter e melhorar a experiência do usuário.',
                ],
                [
                    'title' => 'Newsletter e cancelamento',
                    'body' => 'Inscrições em newsletter podem ser interrompidas a qualquer momento. O objetivo do portal é enviar conteúdo relevante, sem expor sua base de contatos.',
                ],
                [
                    'title' => 'Contato',
                    'body' => 'Para dúvidas sobre privacidade, utilize o canal de contato informado no rodapé do site.',
                ],
            ],
        ]);
    }

    public function terms(): void
    {
        View::render('site/page', [
            'title' => 'Termos de Uso | ' . (string) portal_config('nome_site', 'Estratégia Nerd'),
            'site_chrome' => false,
            'site_meta' => $this->siteMeta(),
            'page_title' => 'Termos de Uso',
            'page_intro' => 'Regras gerais de uso do portal, do conteúdo editorial e das funcionalidades disponíveis.',
            'page_sections' => [
                [
                    'title' => 'Uso do conteúdo',
                    'body' => 'O conteúdo do portal é oferecido para leitura, consulta e acompanhamento editorial. Recomendações, reviews e opiniões são produzidas com critério, mas não substituem verificação própria do usuário.',
                ],
                [
                    'title' => 'Links e recomendações',
                    'body' => 'Algumas indicações podem levar para parceiros, plataformas externas ou campanhas. O usuário é sempre livre para decidir se deseja prosseguir.',
                ],
                [
                    'title' => 'Disponibilidade',
                    'body' => 'O portal pode atualizar, remover ou reorganizar conteúdo e funcionalidades conforme a evolução do projeto.',
                ],
                [
                    'title' => 'Boa utilização',
                    'body' => 'Não é permitido utilizar formulários, comentários ou recursos do site para envio abusivo, spam ou qualquer uso que prejudique o portal.',
                ],
            ],
        ]);
    }

    private function siteMeta(): array
    {
        return [
            'name' => (string) portal_config('nome_site', 'Estratégia Nerd'),
            'description' => (string) portal_config('descricao_site', 'Conteúdo, tecnologia, cultura geek e oportunidades em um só lugar.'),
            'kicker' => (string) portal_config('site_kicker', 'Portal geek estratégico'),
            'footer' => (string) portal_config('footer_texto', 'Estratégia Nerd - Conteúdo, links e ofertas geek'),
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
