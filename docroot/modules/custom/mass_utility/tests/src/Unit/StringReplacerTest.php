<?php

namespace Drupal\Tests\mass_utility\Unit;

use Drupal\mass_utility\RedirectReplacement\StringSearcher;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for the string replacer.
 */
class StringReplacerTest extends UnitTestCase {

  /**
   * Tests for HTML strings.
   */
  public function getHtmlTestStrings() {
    return [
      // It should run replacements in both attributes and text values.
      [
        '<a href="https://www.mass.gov/1" class="bar">https://www.mass.gov/1</a>',
        '<a href="/1-r" class="bar">https://www.mass.gov/1-r</a>',
        2,
        ['/1', '/1']
      ],
      // It should replace all domains with the preferred base.
      [
        '<a href="http://www.mass.gov/1" class="bar">http://www.mass.gov/1</a>',
        '<a href="/1-r" class="bar">https://www.mass.gov/1-r</a>',
        2,
        ['/1', '/1'],
      ],
      // It should replace all the instances inside a string.
      [
        '<a href="http://www.mass.gov/1"></a><a href="http://www.mass.gov/1"></a>',
        '<a href="/1-r"></a><a href="/1-r"></a>',
        2,
        ['/1', '/1'],
      ],
      [
        '<span>https://www.mass.gov/1 https://www.mass.gov/1</span>',
        '<span>https://www.mass.gov/1-r https://www.mass.gov/1-r</span>',
        2,
        ['/1', '/1'],
      ],
      // URLs that include a replacement won't be replaced unless they are an
      // exact match.
      [
        '<a href="https://www.mass.gov/1/foo"></a>',
        '<a href="https://www.mass.gov/1/foo"></a>',
        0,
        ['/1/foo']
      ],
      // Completely unmatched strings should not be touched.
      [
        '<a href="http://www.mass.gov/nomatch">http://www.mass.gov/nomatch</a>',
        '<a href="http://www.mass.gov/nomatch">http://www.mass.gov/nomatch</a>',
        0,
        ['/nomatch', '/nomatch']
      ],
      // It replaces URLs with fragments.
      [
        '<a href="http://www.mass.gov/1#foo">http://www.mass.gov/1#foo</a>',
        '<a href="/1-r#foo">https://www.mass.gov/1-r#foo</a>',
        2,
        ['/1', '/1']
      ],
      // It preserves HTML entities, including nonbreaking spaces.
      [
        'http://www.mass.gov/1 &nbsp;&gt;&lt;foo',
        'https://www.mass.gov/1-r &nbsp;&gt;&lt;foo',
        1,
        ['/1']
      ],
      // It normalizes newlines.
      [
        "http://www.mass.gov/1 \r\n \r",
        "https://www.mass.gov/1-r \n \n",
        1,
        ['/1']
      ],
      // It preserves language attributes.
      [
        '<span lang="EN">http://www.mass.gov/1</span>',
        '<span lang="EN">https://www.mass.gov/1-r</span>',
        1,
        ['/1']
      ],
    ];
  }

  /**
   * Test HTML string replacement.
   *
   * @dataProvider getHtmlTestStrings
   */
  public function testHtmlReplacements(string $input, string $expected, int $expectedCount) {
    $bases = [
      'http://www.mass.gov',
      'https://www.mass.gov',
    ];
    $replacer = new StringSearcher($bases, [
      '/1' => '/1-r',
    ], TRUE);
    $output = $replacer->replaceHtml($input);
    $this->assertEquals($expected, $output);
    $this->assertEquals($expectedCount, array_sum($replacer->getCounts()));
  }

  /**
   * Tests for HTML string discovery.
   *
   * @dataProvider getHtmlTestStrings
   */
  public function testHtmlDiscovery(string $input, $_a, $_b, $expected) {
    $bases = [
      'http://www.mass.gov',
      'https://www.mass.gov',
    ];
    $replacer = new StringSearcher($bases, [], TRUE);
    $this->assertEquals($expected, $replacer->searchHtml($input));
  }

  /**
   * Tests for simple string replacement.
   */
  public function getTestStrings() {
    return [
      // It should run replacements.
      ['http://www.mass.gov/1', '/1-r', 1, ['/1']],
      // It should replace all instances inside a string.
      [
        'http://www.mass.gov/1 http://www.mass.gov/1', '/1-r /1-r',
        2,
        ['/1', '/1'],
      ],
      // Completely unmatched strings should not be touched.
      [
        'http://www.mass.gov/nomatch',
        'http://www.mass.gov/nomatch',
        0,
        ['/nomatch']
      ],
      // URLs that include a replacement won't be replaced unless they are an
      // exact match.
      [
        'https://www.mass.gov/1/foo',
        'https://www.mass.gov/1/foo',
        0,
        ['/1/foo']
      ],
    ];
  }

  /**
   * Test for simple string replacement.
   *
   * @dataProvider getTestStrings
   */
  public function testStringReplacements(string $input, string $expected, int $expectedCount) {
    $bases = [
      'http://www.mass.gov',
      'https://www.mass.gov',
    ];
    $replacer = new StringSearcher($bases, [
      '/1' => '/1-r',
    ]);
    $output = $replacer->replaceText($input);
    $this->assertEquals($expected, $output);
    $this->assertEquals($expectedCount, array_sum($replacer->getCounts()));
  }

  /**
   * Test for simple string discovery.
   *
   * @dataProvider getTestStrings
   */
  public function testStringDiscovery(string $input, $_a, $_b, $expected) {
    $bases = [
      'http://www.mass.gov',
      'https://www.mass.gov',
    ];
    $replacer = new StringSearcher($bases);
    $this->assertEquals($expected, $replacer->searchText($input));
  }

  /**
   * Tests for URI replacements.
   */
  public function getUriReplacementTests() {
    return [
      // It should replace a node URL with an entity path.
      ['http://www.mass.gov/node', 'entity:node/1', 1],
      // It should replace a media URL with an entity path.
      ['http://www.mass.gov/media', 'entity:media/1', 1],
      // It should replace all other matched urls with a base path.
      ['http://www.mass.gov/file', 'internal:/files/1', 1],
      // It should not change completely unmatched paths.
      ['http://www.mass.gov/foo', 'http://www.mass.gov/foo', 0],
    ];
  }

  /**
   * Test for URI replacements.
   *
   * @dataProvider getUriReplacementTests
   */
  public function testUriReplacements(string $input, string $expected, int $expectedCount) {
    $bases = [
      'http://www.mass.gov',
      'https://www.mass.gov',
    ];
    $replacer = new StringSearcher($bases, [
      '/node' => '/node/1',
      '/media' => '/media/1',
      '/file' => '/files/1',
    ]);
    $output = $replacer->replaceUri($input);
    $this->assertEquals($expected, $output);
    $this->assertEquals($expectedCount, array_sum($replacer->getCounts()));
  }

}
