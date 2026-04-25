<?php
/**
 * -----------------------------------------------------------------------------
 * @file        config/routes.php
 * @project     Estrategia Nerd
 * @purpose     Definir rotas da aplicacao
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

return [
    ['GET',  '/',       [\App\Controllers\Site\HomeController::class, 'index'], null],
    ['GET',  '/robots.txt', [\App\Controllers\Site\SeoController::class, 'robots'], null],
    ['HEAD', '/robots.txt', [\App\Controllers\Site\SeoController::class, 'robots'], null],
    ['GET',  '/sitemap.xml', [\App\Controllers\Site\SeoController::class, 'sitemap'], null],
    ['HEAD', '/sitemap.xml', [\App\Controllers\Site\SeoController::class, 'sitemap'], null],
    ['GET',  '/blog',   [\App\Controllers\Site\BlogController::class, 'index'], null],
    ['GET',  '/blog/{slug}',   [\App\Controllers\Site\BlogController::class, 'category'], null],
    ['GET',  '/central-nerd',   [\App\Controllers\Site\CentralController::class, 'index'], null],
    ['GET',  '/link/{slug}', [\App\Controllers\Site\LinkController::class, 'go'], null],
    ['GET',  '/post/{slug}', [\App\Controllers\Site\PostController::class, 'show'], null],
    ['POST', '/post/{slug}/comentarios', [\App\Controllers\Site\PostController::class, 'comment'], null],
    ['POST', '/post/{slug}/curtir', [\App\Controllers\Site\PostController::class, 'like'], null],
    ['GET',  '/politica-de-privacidade', [\App\Controllers\Site\PagesController::class, 'privacy'], null],
    ['GET',  '/termos-de-uso', [\App\Controllers\Site\PagesController::class, 'terms'], null],
    ['POST', '/newsletter', [\App\Controllers\Site\NewsletterController::class, 'subscribe'], null],

    ['GET',  '/login',  [\App\Controllers\Site\AuthController::class, 'showLogin'], null],
    ['POST', '/login',  [\App\Controllers\Site\AuthController::class, 'login'], null],
    ['POST', '/logout', [\App\Controllers\Site\AuthController::class, 'logout'], null],

    ['GET',  '/admin',               [\App\Controllers\Admin\DashboardController::class, 'index'], 'auth'],
    ['GET',  '/admin/api/dashboard', [\App\Controllers\Admin\DashboardController::class, 'data'], 'auth'],

    ['GET',  '/admin/posts',         [\App\Controllers\Admin\PostsController::class, 'index'], 'auth'],
    ['GET',  '/admin/criar-post',    [\App\Controllers\Admin\PostsController::class, 'create'], 'auth'],
    ['POST', '/admin/criar-post',    [\App\Controllers\Admin\PostsController::class, 'store'], 'auth'],
    ['GET',  '/admin/editar-post',   [\App\Controllers\Admin\PostsController::class, 'edit'], 'auth'],
    ['POST', '/admin/editar-post',   [\App\Controllers\Admin\PostsController::class, 'update'], 'auth'],
    ['POST', '/admin/duplicar-post', [\App\Controllers\Admin\PostsController::class, 'duplicate'], 'auth'],
    ['POST', '/admin/upload-post-imagem', [\App\Controllers\Admin\PostsController::class, 'uploadInlineImage'], 'auth'],
    ['POST', '/admin/copiar-post-imagem-biblioteca', [\App\Controllers\Admin\PostsController::class, 'copyInlineImageFromLibrary'], 'auth'],
    ['POST', '/admin/copiar-post-midia-biblioteca', [\App\Controllers\Admin\PostsController::class, 'copyLibraryMediaToPost'], 'auth'],
    ['POST', '/admin/limpar-post-imagens-orfas', [\App\Controllers\Admin\PostsController::class, 'cleanupOrphanImages'], 'auth'],
    ['POST', '/admin/limpar-post-arquivos-orfos', [\App\Controllers\Admin\PostsController::class, 'cleanupOrphanFiles'], 'auth'],
    ['GET',  '/admin/excluir-post',  [\App\Controllers\Admin\PostsController::class, 'deleteConfirm'], 'auth'],
    ['POST', '/admin/excluir-post',  [\App\Controllers\Admin\PostsController::class, 'destroy'], 'auth'],

    ['GET',  '/admin/categorias',          [\App\Controllers\Admin\CategoriasController::class, 'index'], 'auth'],
    ['GET',  '/admin/criar-categoria',     [\App\Controllers\Admin\CategoriasController::class, 'create'], 'auth'],
    ['POST', '/admin/criar-categoria',     [\App\Controllers\Admin\CategoriasController::class, 'store'], 'auth'],
    ['GET',  '/admin/editar-categoria',    [\App\Controllers\Admin\CategoriasController::class, 'edit'], 'auth'],
    ['POST', '/admin/editar-categoria',    [\App\Controllers\Admin\CategoriasController::class, 'update'], 'auth'],
    ['GET',  '/admin/excluir-categoria',   [\App\Controllers\Admin\CategoriasController::class, 'deleteConfirm'], 'auth'],
    ['POST', '/admin/excluir-categoria',   [\App\Controllers\Admin\CategoriasController::class, 'destroy'], 'auth'],

    ['GET',  '/admin/midia',               [\App\Controllers\Admin\MidiaController::class, 'index'], 'auth'],
    ['POST', '/admin/midia/upload',        [\App\Controllers\Admin\MidiaController::class, 'upload'], 'auth'],
    ['POST', '/admin/midia/limpar-orfas',  [\App\Controllers\Admin\MidiaController::class, 'cleanupOrphans'], 'auth'],
    ['GET',  '/admin/excluir-midia',       [\App\Controllers\Admin\MidiaController::class, 'deleteConfirm'], 'auth'],
    ['POST', '/admin/excluir-midia',       [\App\Controllers\Admin\MidiaController::class, 'destroy'], 'auth'],

    ['GET',  '/admin/newsletter',          [\App\Controllers\Admin\NewsletterController::class, 'index'], 'auth'],
    ['GET',  '/admin/inscritos',           [\App\Controllers\Admin\NewsletterController::class, 'index'], 'auth'],
    ['POST', '/admin/newsletter/status',   [\App\Controllers\Admin\NewsletterController::class, 'updateStatus'], 'auth'],
    ['GET',  '/admin/excluir-inscrito',    [\App\Controllers\Admin\NewsletterController::class, 'deleteConfirm'], 'auth'],
    ['POST', '/admin/excluir-inscrito',    [\App\Controllers\Admin\NewsletterController::class, 'destroy'], 'auth'],

    ['GET',  '/admin/usuarios',          [\App\Controllers\Admin\UsuariosController::class, 'index'], 'auth'],
    ['GET',  '/admin/criar-usuario',     [\App\Controllers\Admin\UsuariosController::class, 'create'], 'auth'],
    ['POST', '/admin/criar-usuario',     [\App\Controllers\Admin\UsuariosController::class, 'store'], 'auth'],
    ['GET',  '/admin/editar-usuario',    [\App\Controllers\Admin\UsuariosController::class, 'edit'], 'auth'],
    ['POST', '/admin/editar-usuario',    [\App\Controllers\Admin\UsuariosController::class, 'update'], 'auth'],
    ['POST', '/admin/usuarios/status',   [\App\Controllers\Admin\UsuariosController::class, 'updateStatus'], 'auth'],
    ['GET',  '/admin/excluir-usuario',   [\App\Controllers\Admin\UsuariosController::class, 'deleteConfirm'], 'auth'],
    ['POST', '/admin/excluir-usuario',   [\App\Controllers\Admin\UsuariosController::class, 'destroy'], 'auth'],
    ['GET',  '/admin/home-e-menus',        [\App\Controllers\Admin\HomeMenusController::class, 'index'], 'auth'],
    ['POST', '/admin/home-e-menus',        [\App\Controllers\Admin\HomeMenusController::class, 'update'], 'auth'],
    ['GET',  '/admin/configuracoes',       [\App\Controllers\Admin\ConfiguracoesController::class, 'index'], 'auth'],
    ['POST', '/admin/configuracoes',       [\App\Controllers\Admin\ConfiguracoesController::class, 'update'], 'auth'],
    ['GET',  '/admin/health',              [\App\Controllers\Admin\HealthCheckController::class, 'index'], 'auth'],

    ['GET',  '/admin/links',               [\App\Controllers\Admin\LinksController::class, 'index'], 'auth'],
    ['GET',  '/admin/criar-link',          [\App\Controllers\Admin\LinksController::class, 'create'], 'auth'],
    ['POST', '/admin/criar-link',          [\App\Controllers\Admin\LinksController::class, 'store'], 'auth'],
    ['GET',  '/admin/editar-link',         [\App\Controllers\Admin\LinksController::class, 'edit'], 'auth'],
    ['POST', '/admin/editar-link',         [\App\Controllers\Admin\LinksController::class, 'update'], 'auth'],
    ['POST', '/admin/links/acao',          [\App\Controllers\Admin\LinksController::class, 'quickAction'], 'auth'],
    ['POST', '/admin/links/reordenar',     [\App\Controllers\Admin\LinksController::class, 'reorder'], 'auth'],
    ['GET',  '/admin/excluir-link',        [\App\Controllers\Admin\LinksController::class, 'deleteConfirm'], 'auth'],
    ['POST', '/admin/excluir-link',        [\App\Controllers\Admin\LinksController::class, 'destroy'], 'auth'],

    ['GET',  '/admin/comentarios',         [\App\Controllers\Admin\ComentariosController::class, 'index'], 'auth'],
    ['GET',  '/admin/responder-comentario',[\App\Controllers\Admin\ComentariosController::class, 'reply'], 'auth'],
    ['POST', '/admin/responder-comentario',[\App\Controllers\Admin\ComentariosController::class, 'storeReply'], 'auth'],
    ['POST', '/admin/moderar-comentario',  [\App\Controllers\Admin\ComentariosController::class, 'moderate'], 'auth'],
    ['GET',  '/admin/excluir-comentario',  [\App\Controllers\Admin\ComentariosController::class, 'deleteConfirm'], 'auth'],
    ['POST', '/admin/excluir-comentario',  [\App\Controllers\Admin\ComentariosController::class, 'destroy'], 'auth'],

    ['GET',  '/local/backup',        [\App\Controllers\Site\BackupToolsController::class, 'index'], null],
    ['POST', '/local/backup',        [\App\Controllers\Site\BackupToolsController::class, 'handle'], null],
    ['GET',  '/local/operacoes',     [\App\Controllers\Site\CentralOperacionalController::class, 'index'], null],
    ['POST', '/local/operacoes',     [\App\Controllers\Site\CentralOperacionalController::class, 'handle'], null],
    ['GET',  '/local/conteudo',      [\App\Controllers\Site\ContentSyncToolsController::class, 'index'], null],
    ['POST', '/local/conteudo',      [\App\Controllers\Site\ContentSyncToolsController::class, 'handle'], null],
    ['GET',  '/local/documentacao',  [\App\Controllers\Site\LocalDocsController::class, 'index'], null],
    ['GET',  '/local/docs',          [\App\Controllers\Site\LocalDocsController::class, 'index'], null],
    ['GET',  '/local/blog-estruturas', [\App\Controllers\Site\LocalDocsController::class, 'blogStructures'], null],
    ['GET',  '/local/estruturas-conteudo', [\App\Controllers\Site\LocalDocsController::class, 'blogStructures'], null],
    ['GET',  '/local/backlog',       [\App\Controllers\Site\LocalDocsController::class, 'backlog'], null],
    ['GET',  '/dev',                 [\App\Controllers\Site\DevController::class, 'index'], null],
];
