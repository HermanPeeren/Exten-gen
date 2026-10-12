<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Helper\PostLimit;

/**
 * A project form PHP cut short is refused, not saved.
 *
 * JCB's demo blueprint puts some 24,100 inputs on the edit screen. PHP reads
 * `max_input_vars` of them - 1000 by default - and drops the rest without a
 * word; the task and the token were at the bottom of the form, so they went
 * too, and the save landed on the projects list with nothing saved and
 * nothing said.
 *
 * The detection rests on where things are in the form as much as on the
 * check, so both are pinned here: the task and the token before any model
 * field, the sentinel after all of them.
 *
 * @since  1.6.5
 */
final class PostLimitTest extends TestCase
{
    private function layout(): string
    {
        return (string) file_get_contents(
            \dirname(__DIR__, 2) . '/src/administrator/components/com_extengen/tmpl/project/edit.php'
        );
    }

    /**
     * What PHP makes of a post cut off after `$limit` variables, in the order
     * they were sent - which is what it does.
     *
     * @param  list<array{string, string}>  $sent  Name and value, in form order.
     *
     * @return array<string, mixed>
     */
    private function truncate(array $sent, int $limit): array
    {
        $body = implode('&', array_map(
            static fn (array $pair): string => urlencode($pair[0]) . '=' . urlencode($pair[1]),
            \array_slice($sent, 0, $limit)
        ));

        parse_str($body, $post);

        return $post;
    }

    /**
     * The form in the order the layout writes it.
     *
     * @return list<array{string, string}>
     */
    private function form(int $modelFields): array
    {
        $form = [
            ['task', 'project.apply'],
            ['0123456789abcdef', '1'],
            [PostLimit::COUNT, (string) ($modelFields + 5)],
            ['jform[name]', 'DemoJ'],
        ];

        for ($i = 0; $i < $modelFields; $i++) {
            $form[] = ['jform[power][power' . $i . '][name]', 'P' . $i];
        }

        $form[] = [PostLimit::SENTINEL, '1'];

        return $form;
    }

    public function testAWholePostIsComplete(): void
    {
        $post = $this->truncate($this->form(10), 1000);

        $this->assertTrue(PostLimit::isComplete($post));
        $this->assertSame(15, PostLimit::received($post));
    }

    public function testACutShortPostIsNotAndSaysByHowMuch(): void
    {
        $post = $this->truncate($this->form(24100), 1000);

        $this->assertFalse(PostLimit::isComplete($post));
        $this->assertSame(1000, PostLimit::received($post));
        $this->assertSame(24105, PostLimit::sent($post));

        // What the refusal needs in order to reach the screen at all.
        $this->assertSame('project.apply', $post['task']);
        $this->assertArrayHasKey('0123456789abcdef', $post);
    }

    public function testNoCountMeansNoClaimAboutOne(): void
    {
        $this->assertSame(0, PostLimit::sent([]));
        $this->assertSame(0, PostLimit::sent([PostLimit::COUNT => 'nonsense']));
    }

    public function testTheLayoutPutsTheTaskAndTheTokenBeforeTheModel(): void
    {
        $layout = $this->layout();
        $model  = strpos($layout, "renderField('name')");

        $this->assertNotFalse($model);

        foreach (['name="task"', "HTMLHelper::_('form.token')", 'PostLimit::COUNT'] as $needle) {
            $at = strpos($layout, $needle);

            $this->assertNotFalse($at, $needle . ' is not in the layout.');
            $this->assertLessThan($model, $at, $needle . ' comes after the first field.');
            $this->assertSame(1, substr_count($layout, $needle), $needle . ' is in the layout twice.');
        }
    }

    public function testTheLayoutPutsTheSentinelLast(): void
    {
        $layout = $this->layout();
        $form   = substr($layout, 0, (int) strpos($layout, '</form>'));

        $this->assertMatchesRegularExpression(
            '/<input type="hidden" name="<\?php echo PostLimit::SENTINEL; \?>" value="1">\s*$/',
            $form,
            'Something comes after the sentinel, and a post cut short there would not be noticed.'
        );
    }
}
