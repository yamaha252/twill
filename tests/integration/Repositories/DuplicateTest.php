<?php

namespace A17\Twill\Tests\Integration\Repositories;

use A17\Twill\Facades\TwillUtil;
use A17\Twill\Models\Block;
use A17\Twill\Tests\Integration\Anonymous\AnonymousModule;
use A17\Twill\Tests\Integration\Behaviors\FileTools;
use A17\Twill\Tests\Integration\TestCase;

class DuplicateTest extends TestCase
{
    use FileTools;

    public function setUp(): void
    {
        parent::setUp();
        config()->set('translatable.locales', ['en']);
    }

    public function testSimpleDuplicateContentWithRevisions(): void
    {
        $module = AnonymousModule::make('leaves', $this->app)
            ->withRevisions()
            ->withFields(['title' => ['translatable' => true]])
            ->boot();

        $model = $module->getRepository()->create([
            'title' => ['en' => 'English title'],
            'active' => ['en' => true],
        ]);

        $this->assertEquals('English title', $model->title);

        $duplicate = $module->getRepository()->duplicate($model->id);

        $this->assertEquals('English title', $model->title);

        $this->assertCount(2, $module->getModelClassName()::get());
    }

    public function testDuplicateWithRevisionsAndBrowser(): void
    {
        $browserModule = AnonymousModule::make('apps', $this->app)
            ->withFields(['title'])
            ->boot();

        $module = AnonymousModule::make('aleaves', $this->app)
            ->withRevisions()
            ->withFields(['title' => ['translatable' => true]])
            ->withBelongsToMany(['apps' => $browserModule->getModelClassName()])
            ->boot();

        $model = $module->getRepository()->create([
            'title' => ['en' => 'English title'],
            'active' => ['en' => true],
            'browsers' => [
                'apps' => [
                    ['id' => $treeId = $browserModule->getModelClassName()::create(['title' => 'demo'])->id],
                ],
            ],
        ]);

        $this->assertCount(1, $model->apps);
        $this->assertEquals($treeId, $model->apps->first()->id);

        $duplicate = $module->getRepository()->duplicate($model->id);

        $this->assertCount(1, $duplicate->apps);
        $this->assertEquals($treeId, $duplicate->apps->first()->id);
    }

    public function testDuplicateWithRevisionsAndBlocksAndJsonRepeaters(): void
    {
        $module = AnonymousModule::make('bleaves', $this->app)
            ->withRevisions()
            ->withFields(['title' => ['translatable' => true], 'repeaterdata' => ['type' => 'json']])
            ->boot();

        $model = $module->getRepository()->create([
            'title' => ['en' => 'English title'],
            'active' => ['en' => true],
            'blocks' => [
                [
                    'type' => 'a17-block-quote',
                    'content' => [
                        'quote' => 'Quote',
                        'author' => 'Variable the first',
                    ],
                    'id' => time(),
                ],
            ],
            'repeaters' => [
                'repeaterdata' => [
                    [
                        'id' => time() + 1,
                        'content' => [
                            'description' => 'Hello world!',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $model->repeaterdata);
        $this->assertEquals('English title', $model->title);
        $this->assertCount(1, $model->blocks);

        $this->assertCount(1, Block::get());

        $duplicate = $module->getRepository()->duplicate($model->id);

        $this->assertCount(1, $model->repeaterdata);
        $this->assertCount(1, $duplicate->blocks);
        $this->assertCount(2, Block::get());

        $this->assertNotEquals($duplicate->blocks->first()->id, $model->blocks->first()->id);
    }
    
    public function testDuplicateWithRevisionsAndRepeaters(): void
    {
        $module = AnonymousModule::make('codes', $this->app)
            ->withRevisions()
            ->withFields(['title' => ['translatable' => true]])
            ->withRepeaters(["\App\Models\Tree"])
            ->boot();

        $repeaterModule = AnonymousModule::make('trees', $this->app)
            ->withBelongsTo(['code' => $module->getModelClassName()])
            ->withFields(['title'])
            ->boot();

        $model = $module->getRepository()->create([
            'title' => ['en' => 'English title'],
            'active' => ['en' => true],
            'repeaters' => [
                'trees' => [
                    [
                        'id' => time(),
                        'title' => 'Hello repeater!'
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $model->trees);

        $preDuplicateTreeId = $model->trees->first()->id;

        // We have to clear the temp store as this would also happen on a real environment.
        TwillUtil::clearTempStore();

        $duplicate = $module->getRepository()->duplicate($model->id);

        $this->assertCount(1, $duplicate->trees);
        $this->assertNotEquals($preDuplicateTreeId, $duplicate->trees->first()->id);
    }

    public function testSimpleDuplicateContentWithoutRevisions(): void
    {
        $module = AnonymousModule::make('hearts', $this->app)
            ->withFields(['title' => ['translatable' => true]])
            ->boot();

        $model = $module->getRepository()->create([
            'title' => ['en' => 'English title'],
            'active' => ['en' => true],
        ]);

        $this->assertEquals('English title', $model->title);

        $duplicate = $module->getRepository()->duplicate($model->id);

        $this->assertEquals('English title', $duplicate->title);

        $this->assertCount(2, $module->getModelClassName()::get());
    }
}
