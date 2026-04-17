UPDATE posts
SET conteudo = REPLACE(
  conteudo,
  '<div class="highlight-box"><strong>Próximo passo</strong><p>Antes de investir em peça nova, valide os gargalos reais em <a href="/post/7-erros-que-estao-acabando-com-o-desempenho-do-seu-pc">7 erros que estão acabando com o desempenho do seu PC</a>.</p></div>',
  '<div class="highlight-box"><strong>Próximo passo</strong><p>Para avançar da teoria para decisão por perfil, continue em <a href="/post/pc-gamer-em-2026-o-que-realmente-vale-a-pena-comprar-sem-cair-em-furada">PC Gamer em 2026: comparativo do que realmente vale a pena comprar</a>.</p></div>'
)
WHERE id = 1;
