const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const ROOT = path.resolve(__dirname, '..');
const PUBLIC_VENDOR = path.resolve(
  ROOT,
  '..', '..', '..', '..',
  'public', 'assets', 'vendor', 'echo-robo'
);

function carregarGuardaRoupa() {
  const contexto = vm.createContext({ console });
  contexto.globalThis = contexto;

  for (const arquivo of [
    path.join(PUBLIC_VENDOR, 'robo-roupas.js'),
    path.join(PUBLIC_VENDOR, 'robo-roupas-echo.js'),
    path.join(ROOT, 'alucard-roupa.js'),
  ]) {
    const codigo = fs.readFileSync(arquivo, 'utf8');
    vm.runInContext(codigo, contexto, { filename: arquivo });
  }

  return contexto.RoboRoupas;
}

test('registra a fantasia de Alucard nos slots oficiais do Echo', () => {
  const roupas = carregarGuardaRoupa();
  assert.deepEqual(
    Array.from(roupas.SETS.alucard.items),
    ['cabeloAlucard', 'golaAlucard', 'trajeAlucard']
  );
  assert.equal(roupas.ITEMS.cabeloAlucard.slot, 'cabeca');
  assert.equal(roupas.ITEMS.golaAlucard.slot, 'rosto');
  assert.equal(roupas.ITEMS.trajeAlucard.slot, 'pescoco');
});

test('preserva fantasias oficiais já existentes', () => {
  const roupas = carregarGuardaRoupa();
  for (const conjunto of ['kratos', 'tyrael', 'darthVader', 'ti', 'yusuke', 'toguro']) {
    assert.ok(roupas.SETS[conjunto], `fantasia oficial ausente: ${conjunto}`);
  }
});

test('veste o conjunto Alucard sem deixar peças de outra fantasia', () => {
  const roupas = carregarGuardaRoupa();
  const guardaRoupa = new roupas.Wardrobe();
  roupas.toggleSet(guardaRoupa, 'kratos', 0, { exclusive: true });
  roupas.toggleSet(guardaRoupa, 'alucard', 1, { exclusive: true });
  assert.deepEqual(
    Object.values(guardaRoupa.outfit()).filter(Boolean).sort(),
    ['cabeloAlucard', 'golaAlucard', 'trajeAlucard'].sort()
  );
});

