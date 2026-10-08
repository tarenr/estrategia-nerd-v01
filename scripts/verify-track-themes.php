<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Services/Instagram/TrackPickerService.php';
use App\Services\Instagram\TrackPickerService;

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE instagram_audio_tracks (id INTEGER PRIMARY KEY,titulo TEXT,artista TEXT,genero TEXT,arquivo_path TEXT,duracao_s INTEGER,origem TEXT,origem_id TEXT,ativo INTEGER)');
$db->exec('CREATE TABLE instagram_posts (id INTEGER PRIMARY KEY,audio_track_id INTEGER,audio_start_seconds INTEGER,agendado_para TEXT,publicado_em TEXT,criado_em TEXT)');
$root = dirname(__DIR__);
$relative = 'uploads/automated-tests/music-theme-' . bin2hex(random_bytes(6));
$directory = $root . '/public/' . $relative;
mkdir($directory, 0755, true);
file_put_contents($directory . '/fixture.bin', 'isolated selector integrity fixture, not an audio codec test');
$hash = hash_file('sha256', $directory . '/fixture.bin');
$catalog = ['tracks' => []];
$insert = $db->prepare('INSERT INTO instagram_audio_tracks VALUES (?,?,?,?,?,?,?,?,1)');
foreach ([1=>['dark','slow'],2=>['retro','fast'],3=>['technology','medium'],4=>['fantasy','medium'],5=>['calm','slow'],6=>['light','medium'],7=>['dark','medium'],8=>['action','fast']] as $id => [$theme,$rhythm]) {
    $insert->execute([$id,$theme,'fixture','epic',$relative . '/fixture.bin',120,'pixabay',(string)$id]);
    $catalog['tracks'][] = ['source'=>'pixabay','sourceId'=>(string)$id,'themes'=>[$theme],'rhythm'=>$rhythm,'sha256'=>$hash];
}
$checks = 0;
$check = static function(string $name, bool $ok) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($name); }
    $checks++; echo "OK {$name}\n";
};
try {
    foreach ([['Diablo e guerra eterna','games','dark'],['Resident Evil e horror','games','dark'],['Skyrim RPG medieval','games','fantasy'],['SNES: nostalgia 16 bits','games','retro'],['Memória RAM e processadores','hardware','technology'],['Como limpar o computador','dicas','calm'],['Filme divertido','cultura','light'],['Valorant FPS competitivo','games','action']] as [$title,$category,$theme]) {
        $choice = (new TrackPickerService($db, [], $catalog))->pick($category,24,['titulo'=>$title]);
        $check($title, ($choice['track']['musical_theme'] ?? '') === $theme);
    }
    $check('Resumo altera tema', TrackPickerService::profileFor('games',['titulo'=>'Uma aventura','resumo'=>'Fantasia medieval e magia'])['theme']==='fantasy');
    $check('Horror vence nostalgia', TrackPickerService::profileFor('games',['titulo'=>'Diablo nostálgico'])['theme']==='dark');
    $picker = new TrackPickerService($db, [], $catalog);
    $first=$picker->pick('games',24,['titulo'=>'Diablo']);
    $second=$picker->pick('games',24,['titulo'=>'Diablo']);
    $third=$picker->pick('games',24,['titulo'=>'Diablo']);
    $check('Reserva evita repetição entre compatíveis', $first['track']['id']!==$second['track']['id'] && !$second['reused']);
    $check('Esgotamento reutiliza somente compatível', $third['reused'] && $third['track']['musical_theme']==='dark');
    $db->exec("INSERT INTO instagram_posts VALUES (1,1,5,'2026-10-01',NULL,NULL)");
    $choice=(new TrackPickerService($db, [], $catalog))->pick('games',24,['titulo'=>'Diablo']);
    $check('Histórico prioriza faixa compatível sem uso', $choice['track']['id']===7);
    $choice=(new TrackPickerService($db, [1], $catalog))->pick('games',24,['titulo'=>'Diablo']);
    $check('Ignore IDs preservado', $choice['track']['id']===1);
    $check('Trecho dentro da duração', $choice['start']>=5 && $choice['start']+24<=115);
    $picker=new TrackPickerService($db, [], $catalog);
    $check('Ritmo explícito respeitado', $picker->pick('games',24,['titulo'=>'Diablo','ritmo'=>'medium'])['track']['id']===7);
    $check('Ritmo incompatível não usa fallback', $picker->pick('games',24,['titulo'=>'Diablo','ritmo'=>'fast'])===null && str_contains((string)$picker->warning(),'Nenhuma trilha'));
    $check('Duração insuficiente avisa', $picker->pick('games',130,['titulo'=>'Diablo'])===null && $picker->warning()!==null);
    $bad=$catalog; foreach($bad['tracks'] as &$entry){$entry['sha256']='invalid';} unset($entry);
    $picker=new TrackPickerService($db, [], $bad);
    $check('Hash incompatível bloqueia todas as faixas', $picker->pick('games',24,['titulo'=>'Diablo'])===null);
    $picker=new TrackPickerService($db, [], ['invalid'=>true]);
    $check('Catálogo inválido degrada com aviso', $picker->pick('games',24,['titulo'=>'Diablo'])===null && $picker->warning()!==null);
    $db->exec("UPDATE instagram_audio_tracks SET arquivo_path='uploads/ausente-musical-test.mp3' WHERE id IN(1,7)");
    $picker=new TrackPickerService($db, [], $catalog);
    $check('Arquivo ausente avisa sem faixa alternativa', $picker->pick('games',24,['titulo'=>'Diablo'])===null && $picker->warning()!==null);
    $restore=$db->prepare('UPDATE instagram_audio_tracks SET arquivo_path=? WHERE id IN(1,7)');
    $restore->execute([$relative.'/fixture.bin']);
    $uncurated=$catalog; $uncurated['tracks']=array_values(array_filter($catalog['tracks'],static fn(array $entry):bool=>!in_array('dark',$entry['themes'],true)));
    $check('Faixa não classificada não entra por gênero', (new TrackPickerService($db, [], $uncurated))->pick('games',24,['titulo'=>'Diablo'])===null);
    $picker->pick('hardware',24,['titulo'=>'Memória RAM']);
    $check('Aviso anterior limpo após escolha válida', $picker->warning()===null);
    $db->exec('UPDATE instagram_audio_tracks SET ativo=0 WHERE id IN(1,7)');
    $picker=new TrackPickerService($db, [], $catalog);
    $check('Sem dark não escolhe retro sem uso', $picker->pick('games',24,['titulo'=>'Diablo'])===null);
    $check('Nenhuma escrita em posts pelo seletor', (int)$db->query('SELECT COUNT(*) FROM instagram_posts')->fetchColumn()===1);
    echo "{$checks} verificações aprovadas; SQLite isolado.\n";
} finally {
    unlink($directory . '/fixture.bin');
    rmdir($directory);
}
