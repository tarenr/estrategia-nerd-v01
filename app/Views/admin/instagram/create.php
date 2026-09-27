<?php
/**
 * @file        app/Views/admin/instagram/create.php
 * @project     Estrategia Nerd
 * @purpose     Tela de criação de post do Instagram (FEAT-010)
 *
 * Variáveis disponíveis (injetadas pelo InstagramController::create()):
 * @var string                        $title
 * @var array<string,mixed>|null      $account      Conta ativa
 * @var array<int,array<string,mixed>> $blog_posts   Posts publicados do blog
 * @var string                        $csrf_token
 * @var string|null                   $error        Mensagem de erro de validação
 * @var array<string,mixed>           $old          Valores anteriores do formulário
 */

declare(strict_types=1);

// TODO (Claude Code): implementar a view conforme especificação FEAT-010:
//   - Seletor visual de tipo: Imagem Única | Carrossel (2–10) | Reels (9:16) | Story
//   - Upload múltiplo de arquivos (name="medias[]") ou seleção da biblioteca
//   - Editor de legenda com contador dinâmico de caracteres (limite 2.200) e hashtags (limite 30)
//   - Botão "Importar do Blog": abre seletor de post_blog_id, carrega capa+título+resumo
//     via GET /admin/instagram/api/blog-post?id=X
//   - Preview interativo simulando mockup do feed do Instagram
//   - Ações: "Publicar Agora" | "Salvar como Rascunho" | "Agendar Publicação"
//     (campo agendado_para datetime-local visível apenas ao selecionar Agendar)
//   - Form: POST /admin/instagram/posts/criar com _csrf_token, tipo, legenda,
//     acao (publicar|rascunho|agendar), agendado_para, post_blog_id, medias[]
?>

<?php $this->layout('layouts/admin', ['title' => $title ?? 'Novo Post — Instagram']); ?>

<p>Criar Post no Instagram — view a implementar pelo Claude Code (FEAT-010).</p>
