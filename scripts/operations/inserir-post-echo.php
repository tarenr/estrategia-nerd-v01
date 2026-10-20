<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

$pdo = $GLOBALS['pdo'];

$titulo = 'Conheça o Echo, o assistente do Estratégia Nerd';
$slug = 'conheca-o-echo-assistente-estrategia-nerd';
$resumo = 'O Echo é o robô de mesa do Estratégia Nerd: acompanha o Claude Code, o Antigravity e o Codex, avisa quando precisa de você, responde por voz e até se fantasia.';
$categoria = 'gadgets';
$categoria_id = 1;
$categoria_post_id = 1;
$tipo_post = 'artigo';
$imagem_capa = 'uploads/posts/conheca-o-echo-assistente-estrategia-nerd/images/capa.webp';
$imagem_thumb = 'uploads/posts/conheca-o-echo-assistente-estrategia-nerd/images/thumb.webp';
$autor_id = 1;
$status = 'rascunho';
$destaque = 1;
$tempo_leitura = 6;
$tags = 'Echo, Assistente Virtual, Claude Code, Antigravity, Codex CLI, IA, Dev Setup, Gadgets, Robô';
$seo_title = 'Conheça o Echo: o robô assistente de mesa do Estratégia Nerd';
$seo_description = 'O Echo é o robô de mesa do Estratégia Nerd: monitora o Claude Code, o Antigravity e o Codex, responde por voz e avisa quando precisa de você.';
$seo_keywords = 'Echo, robô de mesa, assistente IA, Claude Code, Antigravity, Codex, dev setup, Estratégia Nerd';

$conteudo = <<<'HTML'
<!-- VÍDEO PRINCIPAL DO ARTIGO -->
<figure>
  <video controls preload="metadata" poster="uploads/posts/conheca-o-echo-assistente-estrategia-nerd/images/capa.webp" style="width: 100%; border-radius: 0.75rem; aspect-ratio: 16/9; background-color: #0f172a;">
    <source src="uploads/posts/conheca-o-echo-assistente-estrategia-nerd/video/echo-detalhado.mp4" type="video/mp4">
    Seu navegador não suporta a tag de vídeo.
  </video>
  <figcaption>O Echo em 1 min 44 s. Os dados dos cartões são de demonstração.</figcaption>
</figure>

<!-- INTRODUÇÃO -->
<p>
  O <strong>Estratégia Nerd</strong> tem um assistente. O nome dele é <strong>Echo</strong> e ele mora na mesa, ao lado do computador. Ele não escreve os artigos nem mexe no site: o trabalho dele é ficar de olho em tudo que acontece no PC e avisar a gente, do jeito mais simples e direto possível.
</p>

<p>
  Neste artigo mostramos o que ele faz, como funciona a sua arquitetura por dentro e por que ele até ganhou uma fantasia temática de Dia das Bruxas.
</p>

<!-- RESUMO RÁPIDO -->
<div style="margin: 2rem 0; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid rgba(0, 229, 255, 0.3); background-color: rgba(15, 23, 42, 0.8);">
  <strong style="color: #00e5ff; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.05em; display: block; margin-bottom: 0.75rem;">
    • Resumo Rápido
  </strong>
  <ul style="margin: 0; padding-left: 1.25rem; line-height: 1.8;">
    <li><strong>Robô de mesa</strong> que roda no navegador do smartphone ou do computador.</li>
    <li><strong>Vigia os agentes de IA:</strong> mostra o que o Claude Code, o Google Antigravity e o Codex CLI estão fazendo.</li>
    <li><strong>Alertas em tempo real:</strong> avisa quando precisa de permissão, quando a tarefa termina e quando ocorre erro.</li>
    <li><strong>Responde por voz</strong> natural brasileira com cartões de dados compactos.</li>
    <li><strong>Memória e personalidade:</strong> lembra do que você pede, reage a toques e expressa emoções.</li>
  </ul>
</div>

<!-- O QUE É O ECHO -->
<h2>O que é o Echo</h2>

<p>
  O Echo é um robô-companheiro de mesa. Ele foi projetado para rodar como aplicativo web no navegador de um smartphone — de preferência com tela OLED acoplado a um suporte na mesa —, conectado na mesma rede Wi-Fi do computador ou acessado remotamente por um túnel seguro protegido por PIN.
</p>

<p>
  Ele se adapta dinamicamente à orientação do aparelho: na vertical (modo retrato) o robô ocupa a metade superior da tela e abre os cartões embaixo; na horizontal (modo paisagem) ele vai para a esquerda e os cartões ficam à direita.
</p>

<figure>
  <img src="uploads/posts/conheca-o-echo-assistente-estrategia-nerd/images/img-001.webp" alt="O Echo em modo paisagem à esquerda e modo retrato à direita no celular">
  <figcaption>Design híbrido: o robô se reorganiza dinamicamente entre as orientações horizontal e vertical.</figcaption>
</figure>

<!-- ELE VIGIA AS IAS -->
<h2>Ele vigia as IAs</h2>

<p>
  O grande diferencial do Echo no nosso dia a dia de desenvolvimento é o monitoramento contínuo das ferramentas de inteligência artificial. O robô recebe um aviso a cada ação executada pelas IAs que trabalham nos projetos: o <strong>Claude Code</strong>, o <strong>Google Antigravity</strong> e o <strong>Codex CLI</strong>.
</p>

<h3>Uma cor para cada IA</h3>

<p>
  Cada inteligência artificial possui uma cor própria. Quando um agente entra em ação, o robô sincroniza sua cor imediatamente:
</p>

<div style="margin: 1.5rem 0; overflow-x: auto;">
  <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.95rem;">
    <thead>
      <tr style="border-bottom: 2px solid #334155;">
        <th style="padding: 0.75rem;">Quem</th>
        <th style="padding: 0.75rem;">Cor</th>
        <th style="padding: 0.75rem;">Quando</th>
      </tr>
    </thead>
    <tbody>
      <tr style="border-bottom: 1px solid #1e293b;">
        <td style="padding: 0.75rem; color: #ff7a45; font-weight: 600;">Claude Code</td>
        <td style="padding: 0.75rem;">Coral</td>
        <td style="padding: 0.75rem;">Trabalhando no código</td>
      </tr>
      <tr style="border-bottom: 1px solid #1e293b;">
        <td style="padding: 0.75rem; color: #00e5ff; font-weight: 600;">Google Antigravity</td>
        <td style="padding: 0.75rem;">Azul-ciano</td>
        <td style="padding: 0.75rem;">Trabalhando no código</td>
      </tr>
      <tr style="border-bottom: 1px solid #1e293b;">
        <td style="padding: 0.75rem; color: #00f59b; font-weight: 600;">Codex CLI</td>
        <td style="padding: 0.75rem;">Verde-menta</td>
        <td style="padding: 0.75rem;">Trabalhando no código</td>
      </tr>
      <tr style="border-bottom: 1px solid #1e293b;">
        <td style="padding: 0.75rem; color: #fbbf24; font-weight: 600;">Você precisa agir</td>
        <td style="padding: 0.75rem;">Âmbar</td>
        <td style="padding: 0.75rem;">Pedido de permissão ou pergunta</td>
      </tr>
      <tr style="border-bottom: 1px solid #1e293b;">
        <td style="padding: 0.75rem; color: #10b981; font-weight: 600;">Concluído</td>
        <td style="padding: 0.75rem;">Esmeralda</td>
        <td style="padding: 0.75rem;">Tarefa terminada com sucesso</td>
      </tr>
      <tr>
        <td style="padding: 0.75rem; color: #f43f5e; font-weight: 600;">Erro</td>
        <td style="padding: 0.75rem;">Vermelho-rosado</td>
        <td style="padding: 0.75rem;">Algo falhou no terminal</td>
      </tr>
    </tbody>
  </table>
</div>

<figure>
  <img src="uploads/posts/conheca-o-echo-assistente-estrategia-nerd/images/img-002.webp" alt="O Echo exibindo as cores de cada agente de IA: coral, ciano, verde e o modo dual neon">
  <figcaption>Uma cor para cada IA: Coral (Claude Code), Ciano (Antigravity), Verde (Codex) e o modo multiagente.</figcaption>
</figure>

<h3>Vários agentes ao mesmo tempo</h3>

<p>
  Quando dois agentes trabalham em conjunto no computador, o topo da tela exibe o nome de ambos e o robô pulsa em um efeito sincronizado entre as duas cores. Se uma das IAs conclui o trabalho, o Echo notifica a finalização e continua acompanhando a outra sem interromper o painel.
</p>

<h3>Cada passo na tela</h3>

<p>
  Abaixo do cabeçalho da IA, uma faixa de atividade mostra o passo atual e os três anteriores mais apagados: leitura de arquivos, escrita, comando, permissão, pergunta e conclusão.
</p>

<p>
  Por privacidade, o Echo nunca recebe o comando inteiro. Chegam apenas o programa e o primeiro argumento (por exemplo, <code>Bash: npm test</code>), e valores longos ou do tipo <code>CHAVE=valor</code> aparecem escondidos como <code>***</code>.
</p>

<h3>Como ele sabe o que as IAs fazem</h3>

<p>
  Cada IA avisa o Echo por um gancho próprio: os hooks do Claude Code, o arquivo de hooks do Antigravity e o aviso de fim de turno do Codex CLI.
</p>

<!-- FLUXO SEMÂNTICO DE ARQUITETURA EM HTML REAL (SEM IMAGEM COM TEXTO PURO) -->
<div style="margin: 2rem 0; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #334155; background-color: #0b0f19;">
  <strong style="color: #38bdf8; display: block; margin-bottom: 1rem; font-size: 0.9rem; text-transform: uppercase;">
    Fluxo de Comunicação dos Agentes
  </strong>
  <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; font-size: 0.9rem;">
    <div style="flex: 1; min-width: 140px; padding: 0.75rem; border-radius: 0.5rem; background: #1e293b; text-align: center;">
      <span style="display: block; font-weight: 600; color: #ff7a45;">1. Agentes IA</span>
      <span style="font-size: 0.8rem; color: #94a3b8;">Hooks nativos</span>
    </div>
    <span style="color: #38bdf8; font-weight: bold;">→</span>
    <div style="flex: 1; min-width: 140px; padding: 0.75rem; border-radius: 0.5rem; background: #1e293b; text-align: center;">
      <span style="display: block; font-weight: 600; color: #00e5ff;">2. Despachante</span>
      <span style="font-size: 0.8rem; color: #94a3b8;">Porta 4884 (&le; 300ms)</span>
    </div>
    <span style="color: #38bdf8; font-weight: bold;">→</span>
    <div style="flex: 1; min-width: 140px; padding: 0.75rem; border-radius: 0.5rem; background: #1e293b; text-align: center;">
      <span style="display: block; font-weight: 600; color: #10b981;">3. Servidor Echo</span>
      <span style="font-size: 0.8rem; color: #94a3b8;">Node.js + SSE + TTS</span>
    </div>
    <span style="color: #38bdf8; font-weight: bold;">→</span>
    <div style="flex: 1; min-width: 140px; padding: 0.75rem; border-radius: 0.5rem; background: #1e293b; text-align: center;">
      <span style="display: block; font-weight: 600; color: #c084fc;">4. Smartphone</span>
      <span style="font-size: 0.8rem; color: #94a3b8;">Wi-Fi ou Túnel + PIN</span>
    </div>
  </div>
</div>

<p>
  Um pequeno despachante envia o evento para o servidor do Echo. Ele nunca atrasa a IA: espera no máximo 300 milissegundos e termina sempre sem erro, mesmo com o Echo desligado.
</p>

<!-- PERMISSÃO, CONCLUSÃO E ERRO -->
<h2>Permissão, conclusão e erro</h2>

<p>
  Quando uma IA precisa de permissão, o robô fica âmbar, mostra um ponto de exclamação e avisa por voz. Quando termina, fica esmeralda. Se dá erro, fica vermelho e alerta. Os alertas têm prioridade até sobre a escuta do microfone.
</p>

<figure>
  <img src="uploads/posts/conheca-o-echo-assistente-estrategia-nerd/images/img-003.webp" alt="O Echo nos três estados de alerta: âmbar para permissão, esmeralda para concluído e vermelho para erro">
  <figcaption>Alertas visuais instantâneos: permissão (âmbar), conclusão com sucesso (esmeralda) e erro (vermelho).</figcaption>
</figure>

<!-- APROVAR PELO CELULAR -->
<h2>Aprovar pelo celular</h2>

<p>
  Com o modo celular ligado, os pedidos de permissão e as perguntas do Claude Code aparecem num cartão no Echo, e você responde de onde estiver. O cartão mostra a ferramenta, o projeto e um contador de tempo, com os botões PERMITIR, NEGAR e “Responder no terminal”. Para decidir, é preciso um segundo PIN, e cada pedido vale por até dois minutos e aceita uma única resposta.
</p>

<div style="margin: 1.5rem 0; padding: 1rem; border-left: 4px solid #fbbf24; background-color: rgba(251, 191, 36, 0.1); font-size: 0.95rem;">
  <strong>Destaque:</strong> esse modo funciona só com o Claude Code. Codex e Antigravity não permitem esperar uma decisão remota no fluxo de hooks.
</div>

<figure>
  <img src="uploads/posts/conheca-o-echo-assistente-estrategia-nerd/images/img-004.webp" alt="Cartão de aprovação remota do Claude Code na tela do celular">
  <figcaption>Modo celular: cartão de aprovação com contagem regressiva e botões de decisão protegidos por segundo PIN.</figcaption>
</figure>

<!-- PERGUNTE POR VOZ -->
<h2>Pergunte por voz</h2>

<p>
  O Echo escuta em modo mãos-livres e responde com uma voz brasileira natural. As respostas vêm em cartões compactos: o assunto no selo, o número principal no título e uma linha por item com uma bolinha de status (verde, âmbar ou vermelha).
</p>

<h3>O que dá para perguntar</h3>

<ul>
  <li>Backups, bancos, cartões e contas</li>
  <li>Tarefas do Forge</li>
  <li>Posts agendados do Estratégia Nerd</li>
  <li>Treinos do Gym OS</li>
  <li>Desempenho do PC</li>
</ul>

<figure>
  <img src="uploads/posts/conheca-o-echo-assistente-estrategia-nerd/images/img-005.webp" alt="Quatro cartões de respostas por voz: backups, tarefas do Forge, treino e telemetria do computador">
  <figcaption>Respostas estruturadas por voz em cartões compactos para consultas rápidas do setup.</figcaption>
</figure>

<p style="font-size: 0.9rem; color: #94a3b8; font-style: italic;">
  Os cartões do vídeo e deste artigo usam dados de demonstração.
</p>

<!-- ELE LEMBRA DO QUE VOCÊ PEDE -->
<h2>Ele lembra do que você pede</h2>

<p>
  O Echo guarda fatos que você ensina, só neste computador. Exemplos de comando:
</p>

<ul>
  <li><em>“Lembre bebida preferida: café”</em></li>
  <li><em>“O que você lembra sobre mim?”</em></li>
  <li><em>“Esqueça bebida preferida”</em></li>
</ul>

<p>
  Senhas, chaves e tokens nunca são aceitos como fatos.
</p>

<!-- PERSONALIDADE -->
<h2>Personalidade</h2>

<p>
  O Echo reage ao toque, e cada reação tem um jeito:
</p>

<div style="margin: 1.5rem 0; overflow-x: auto;">
  <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.95rem;">
    <thead>
      <tr style="border-bottom: 2px solid #334155;">
        <th style="padding: 0.75rem;">Gesto</th>
        <th style="padding: 0.75rem;">Reação</th>
      </tr>
    </thead>
    <tbody>
      <tr style="border-bottom: 1px solid #1e293b;">
        <td style="padding: 0.75rem;">Um toque</td>
        <td style="padding: 0.75rem;">Ele achata</td>
      </tr>
      <tr style="border-bottom: 1px solid #1e293b;">
        <td style="padding: 0.75rem;">Dois toques rápidos</td>
        <td style="padding: 0.75rem;">Pula e gira</td>
      </tr>
      <tr style="border-bottom: 1px solid #1e293b;">
        <td style="padding: 0.75rem;">Cinco toques seguidos</td>
        <td style="padding: 0.75rem;">Fica bravo</td>
      </tr>
      <tr style="border-bottom: 1px solid #1e293b;">
        <td style="padding: 0.75rem;">Toque no visor</td>
        <td style="padding: 0.75rem;">Surpresa</td>
      </tr>
      <tr style="border-bottom: 1px solid #1e293b;">
        <td style="padding: 0.75rem;">Passar o dedo na cabeça</td>
        <td style="padding: 0.75rem;">Carinho</td>
      </tr>
      <tr>
        <td style="padding: 0.75rem;">Segurar</td>
        <td style="padding: 0.75rem;">Abre o guarda-roupa</td>
      </tr>
    </tbody>
  </table>
</div>

<p>
  Parado por 3 minutos, ele pega um café. Parado por 6, dorme. E, se você pedir, ele dança.
</p>

<figure>
  <img src="uploads/posts/conheca-o-echo-assistente-estrategia-nerd/images/img-006.webp" alt="Expressões orgânicas do Echo: carinho na cabeça com corações, reação de irritação e dança">
  <figcaption>Personalidade expressiva: carinho com bochechas coradas, reação de irritação e dança com pulinhos.</figcaption>
</figure>

<!-- GUARDA-ROUPA E DIA DAS BRUXAS -->
<h2>Guarda-roupa e Dia das Bruxas</h2>

<p>
  O Echo tem 11 peças comuns e uma fantasia de esqueleto, com sorriso costurado e terno. Com o Dia das Bruxas chegando, ele já se vestiu de Jack. A Luna, a assistente pessoal do Estratégia Nerd e esposa do Echo, está de Sally, com fantasia de boneca de pano.
</p>

<figure>
  <img src="uploads/posts/conheca-o-echo-assistente-estrategia-nerd/images/img-007.webp" alt="Echo fantasiado de Jack Skellington e Luna vestida de Sally">
  <figcaption>Especial de Halloween: Echo fantasiado de Jack Skellington e Luna como Sally.</figcaption>
</figure>

<!-- PERGUNTAS FREQUENTES (FAQ) -->
<h2>Perguntas frequentes</h2>

<div style="margin: 2rem 0; display: flex; flex-direction: column; gap: 1rem;">
  <div style="padding: 1.25rem; border-radius: 0.75rem; border: 1px solid #334155; background-color: rgba(15, 23, 42, 0.6);">
    <strong style="color: #38bdf8; display: block; margin-bottom: 0.5rem;">O Echo precisa de internet?</strong>
    <p style="margin: 0; font-size: 0.95rem; color: #cbd5e1;">
      <em>Para o monitoramento e os comandos locais, não; para a experiência completa de voz e IA, sim.</em> O acompanhamento dos agentes de IA, a telemetria do PC, as reações ao toque e a interface na mesma rede Wi-Fi rodam 100% de forma local no seu computador, sem depender de conexão externa. Porém, para a voz neural (Edge TTS / Gemini), as perguntas livres que usam a API do Gemini e o acesso remoto fora de casa (pelo celular em 4G/5G com PIN), a conexão com a internet é necessária.
    </p>
  </div>

  <div style="padding: 1.25rem; border-radius: 0.75rem; border: 1px solid #334155; background-color: rgba(15, 23, 42, 0.6);">
    <strong style="color: #38bdf8; display: block; margin-bottom: 0.5rem;">Funciona com qualquer IA?</strong>
    <p style="margin: 0; font-size: 0.95rem; color: #cbd5e1;">
      Hoje acompanha o Claude Code, o Antigravity e o Codex através dos ganchos de cada ferramenta.
    </p>
  </div>

  <div style="padding: 1.25rem; border-radius: 0.75rem; border: 1px solid #334155; background-color: rgba(15, 23, 42, 0.6);">
    <strong style="color: #38bdf8; display: block; margin-bottom: 0.5rem;">Os dados do vídeo são reais?</strong>
    <p style="margin: 0; font-size: 0.95rem; color: #cbd5e1;">
      Não. Backups, tarefas, treinos e posts do vídeo são de demonstração.
    </p>
  </div>

  <div style="padding: 1.25rem; border-radius: 0.75rem; border: 1px solid #334155; background-color: rgba(15, 23, 42, 0.6);">
    <strong style="color: #38bdf8; display: block; margin-bottom: 0.5rem;">É um produto?</strong>
    <p style="margin: 0; font-size: 0.95rem; color: #cbd5e1;">
      <em>Não.</em> O Echo é um projeto autoral e assistente oficial dos bastidores do Estratégia Nerd, desenvolvido sob medida para o nosso fluxo de trabalho com agentes de IA e automação do PC. O código é aberto no GitHub sob licença livre (ISC), mas ele não é um produto comercial ou serviço à venda.
    </p>
  </div>
</div>

<!-- FECHAMENTO -->
<h2>Fechamento</h2>

<p>
  O Echo existe para tirar da frente o que é rotina e deixar a gente focado no que importa. Se quiser ver como ele funciona na prática, o vídeo está logo no começo do artigo.
</p>
HTML;

try {
    $stmtUpdate = $pdo->prepare('
        UPDATE posts SET
            titulo = :titulo,
            resumo = :resumo,
            conteudo = :conteudo,
            categoria = :categoria,
            categoria_id = :categoria_id,
            categoria_post_id = :categoria_post_id,
            tipo_post = :tipo_post,
            imagem_capa = :imagem_capa,
            imagem_thumb = :imagem_thumb,
            autor_id = :autor_id,
            status = :status,
            destaque = :destaque,
            tempo_leitura = :tempo_leitura,
            tags = :tags,
            seo_title = :seo_title,
            seo_description = :seo_description,
            seo_keywords = :seo_keywords,
            data_atualizacao = NOW()
        WHERE slug = :slug
    ');
    $stmtUpdate->execute([
        'titulo' => $titulo,
        'resumo' => $resumo,
        'conteudo' => $conteudo,
        'categoria' => $categoria,
        'categoria_id' => $categoria_id,
        'categoria_post_id' => $categoria_post_id,
        'tipo_post' => $tipo_post,
        'imagem_capa' => $imagem_capa,
        'imagem_thumb' => $imagem_thumb,
        'autor_id' => $autor_id,
        'status' => $status,
        'destaque' => $destaque,
        'tempo_leitura' => $tempo_leitura,
        'tags' => $tags,
        'seo_title' => $seo_title,
        'seo_description' => $seo_description,
        'seo_keywords' => $seo_keywords,
        'slug' => $slug,
    ]);

    echo "Post atualizado com sucesso no banco de dados local!\n";
    echo "Slug: {$slug}\n";
    echo "Capa: {$imagem_capa}\n";
    echo "Thumb: {$imagem_thumb}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Erro ao atualizar post: " . $e->getMessage() . "\n");
    exit(1);
}
