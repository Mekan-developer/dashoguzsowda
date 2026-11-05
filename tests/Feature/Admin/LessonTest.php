<?php

use App\Models\User;
use App\Services\LessonService;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Раздел «Обучение»: статический сайт из storage/app/lessons в iframe админки.
 * Материалы только для admin, поэтому и файлы отдаются через роут с проверкой
 * роли, а не из public/.
 */
beforeEach(function () {
    Storage::fake('lessons');

    $this->admin = User::factory()->admin()->create();
});

it('показывает раздел, когда материалы загружены', function () {
    Storage::disk('lessons')->put('index.html', '<h1>Lessons</h1>');

    $this->actingAs($this->admin)
        ->get(route('lessons.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Lessons/Index')->where('installed', true));
});

it('сообщает, что материалов на сервере нет', function () {
    $this->actingAs($this->admin)
        ->get(route('lessons.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('installed', false));
});

it('отдаёт файлы с типом, который браузер примет', function (string $path, string $mime) {
    Storage::disk('lessons')->put($path, 'content');

    $response = $this->actingAs($this->admin)
        ->get(route('lessons.file', ['path' => $path]))
        ->assertOk()
        ->assertHeader('Content-Type', $mime);

    expect($response->baseResponse->getFile()->getContent())->toBe('content');
})->with([
    'страница'  => ['lessons/tarify.html', 'text/html; charset=utf-8'],
    'стили'     => ['assets/base.css', 'text/css; charset=utf-8'],
    'скрипт'    => ['assets/engine.js', 'application/javascript; charset=utf-8'],
    'картинка'  => ['assets/logo.png', 'image/png'],
    'озвучка'   => ['audio/ru/tarify-01.mp3', 'audio/mpeg'],
]);

it('отдаёт озвучку по частям', function () {
    Storage::disk('lessons')->put('audio/ru/push-01.mp3', '0123456789');

    $this->actingAs($this->admin)
        ->get(route('lessons.file', ['path' => 'audio/ru/push-01.mp3']), ['Range' => 'bytes=2-5'])
        ->assertStatus(206)
        ->assertHeader('Content-Range', 'bytes 2-5/10');
});

it('отвечает 404 на несуществующий файл и на тип не из списка', function (string $path) {
    Storage::disk('lessons')->put('notes.php', '<?php');
    Storage::disk('lessons')->put('index.html:Zone.Identifier', 'junk');

    $this->actingAs($this->admin)
        ->get(route('lessons.file', ['path' => $path]))
        ->assertNotFound();
})->with(['missing.html', 'notes.php', 'index.html:Zone.Identifier']);

it('не выпускает за пределы папки с материалами', function () {
    Storage::disk('lessons')->put('index.html', 'ok');
    $outside = dirname(Storage::disk('lessons')->path('index.html')).'/../lessons-secret.html';
    file_put_contents($outside, 'secret');

    try {
        expect(app(LessonService::class)->resolve('../lessons-secret.html'))->toBeNull();

        $this->actingAs($this->admin)
            ->get('/admin/lessons/files/..%2Flessons-secret.html')
            ->assertNotFound();
    } finally {
        unlink($outside);
    }
});

it('закрывает раздел и файлы для менеджера', function () {
    Storage::disk('lessons')->put('index.html', 'ok');
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)->get(route('lessons.index'))->assertForbidden();
    $this->actingAs($manager)->get(route('lessons.file', ['path' => 'index.html']))->assertForbidden();
});

it('не отдаёт файлы без входа', function () {
    Storage::disk('lessons')->put('index.html', 'ok');

    $this->get(route('lessons.file', ['path' => 'index.html']))->assertRedirect(route('login'));
});
