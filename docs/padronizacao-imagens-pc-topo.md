# Padronização das imagens do PC Gamer Topo

Data: 2026-10-05.

## Escopo

Nove capas locais do grupo `PC Gamer Topo` (links 29–37), editadas com imagegen a partir das imagens originais. Referência visual: prévia da Galax RTX 5080 aprovada pelo usuário. Fundo tecnológico escuro, iluminação ciano com detalhes dourados e superfície reflexiva. Os produtos originais serviram como referência; são artes geradas, não fotografias técnicas para verificação de conectores ou inscrições pequenas.

## Arquivos substituídos

- `public/uploads/links/processador-amd-ryzen-7-9800x3d-104mb-4-7ghz-5-2ghz/capa.webp`
- `public/uploads/links/placa-mae-msi-mag-x870-tomahawk-wifi-ddr5-am5-atx-x870/capa.webp`
- `public/uploads/links/placa-de-video-galax-nvidia-rtx-5080-16gb-gddr7-256-bits-pci-e-5-0/capa.webp`
- `public/uploads/links/water-cooler-gamdias-aura-gl360-lite-preto-argb-am5-lga-1851/capa.webp`
- `public/uploads/links/kingston-fury-beast-rgb-32gb-2x16gb-6000mt-s-ddr5-cl30/capa.webp`
- `public/uploads/links/ssd-m-2-nvme-1tb-pcie-4-0-2280-kingston-kc3000-com-dissipador-de-calor-skc3000s-1024g/capa.webp`
- `public/uploads/links/placa-grafica-gigabyte-geforce-rtx-4070-ti-super-gaming-o/capa.webp`
- `public/uploads/links/fonte-corsair-rm850e-850w-cybenetics-gold-full-modular/capa.webp`
- `public/uploads/links/gabinete-gamer-nzxt-h6-flow-mid-tower/capa.webp`

## Formato e recuperação

WebP, 1080 × 1080 pixels, qualidade 90. Formato, dimensões, decodificação e diferença de SHA-256 frente aos originais foram verificados. Os nove originais estão em `storage/backups/pc-topo-imagens/{slug}.webp`. Para recuperar, copiar o original correspondente para o caminho de capa acima.

O cadastro de produtos e os caminhos no banco foram preservados. Aplicação somente local; publicação/sincronização para produção não faz parte desta tarefa. Arquivos de uploads e backups podem estar ignorados pelo Git.

## Validação

As nove imagens geradas foram examinadas visualmente antes da aplicação. Suíte obrigatória: `C:\xampp\php\php.exe scripts/verify-changes.php`. Verificação anterior à substituição: 17 aprovadas, zero falhas; verificação final após aplicação registrada abaixo.

Verificação final após a substituição: **APROVADO COM SUCESSO — 17 testes aprovados, 0 falhas**, incluindo PHPStan e renderização dos ambientes local, produção e stage. Execução fora do sandbox necessária para acesso ao banco e às sessões. Banco, código da aplicação, Git e publicação não foram alterados pela tarefa.
