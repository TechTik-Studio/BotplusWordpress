<?php

namespace BotPlus\Plugin\Tests\Unit;

use PHPUnit\Framework\TestCase;
use BotPlus\Plugin\Sync\BuilderExtractor;

class BuilderExtractorTest extends TestCase
{
    protected BuilderExtractor $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new BuilderExtractor();
    }

    public function testParseElementorTreeExtractsHeadingsAndText(): void
    {
        $mockTree = [
            [
                'id' => 'sec_1',
                'elType' => 'section',
                'elements' => [
                    [
                        'id' => 'col_1',
                        'elType' => 'column',
                        'elements' => [
                            [
                                'id' => 'w_1',
                                'elType' => 'widget',
                                'widgetType' => 'heading',
                                'settings' => [
                                    'title' => 'Direct Expansion Bulk Milk Coolers',
                                    'title_color' => '#1e293b', // Should be ignored
                                ],
                            ],
                            [
                                'id' => 'w_2',
                                'elType' => 'widget',
                                'widgetType' => 'text-editor',
                                'settings' => [
                                    'editor' => '<p>Engineered for high thermal efficiency with laser-welded evaporator plates.</p>',
                                ],
                            ],
                            [
                                'id' => 'w_3',
                                'elType' => 'widget',
                                'widgetType' => 'icon-list',
                                'settings' => [
                                    'icon_list' => [
                                        ['text' => 'Rapid cooling from 35C to 4C in under 3 hours'],
                                        ['text' => 'Digital temperature controller with automatic agitation'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $extracted = $this->builder->parseElementorTree($mockTree);

        $this->assertContains('Direct Expansion Bulk Milk Coolers', $extracted);
        $this->assertContains('<p>Engineered for high thermal efficiency with laser-welded evaporator plates.</p>', $extracted);
        $this->assertContains('Rapid cooling from 35C to 4C in under 3 hours', $extracted);
        $this->assertContains('Digital temperature controller with automatic agitation', $extracted);
        $this->assertNotContains('#1e293b', $extracted);
    }

    public function testExtractElementorContentReturnsEmptyStringWhenWpNotLoaded(): void
    {
        $result = $this->builder->extractElementorContent(999);
        $this->assertEquals('', $result);
    }
}

