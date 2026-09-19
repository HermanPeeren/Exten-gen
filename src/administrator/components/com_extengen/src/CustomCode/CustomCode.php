<?php

/**
 * @package     Extengen
 * @subpackage  CustomCode
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\CustomCode;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Yepr\Gen\Core\Output\ProtectedRegionMerger;

/**
 * The custom code one model object carries, ready for a template to emit.
 *
 * A generator asks for `regions($entity, 'Entity')` and gets back one string
 * per slot, marked up as a protected region. A template emits the slot it has
 * room for and never sees a body directly, which is what keeps the markers in
 * one place: a template that wrote its own would drift from the pattern the
 * merger matches, and the drift would only show up as somebody's code quietly
 * failing to come back after a regeneration.
 *
 * Every slot gets a region, filled or empty. An empty one is two comment lines
 * in the generated file, and they earn their place: they are where the merger
 * puts back an edit made in the output, and they tell a reader of the generated
 * code where it is allowed to write.
 *
 * @since  1.0.0
 */
final class CustomCode
{
    /**
     * @since  1.0.0
     */
    public function __construct(
        private readonly SlotCatalogue $catalogue = new SlotCatalogue(),
        private readonly ProtectedRegionMerger $merger = new ProtectedRegionMerger(SlotCatalogue::TAG)
    ) {
    }

    /**
     * Rendered regions for every slot this kind of object has, by slot id.
     *
     * @param  ?object  $node      The entity or page from the model.
     * @param  string   $owner     `Entity` or `Page`.
     * @param  ?string  $pageType  Narrows Page slots to one kind of page.
     *
     * @return array<string, string>
     *
     * @since  1.0.0
     */
    public function regions(?object $node, string $owner, ?string $pageType = null): array
    {
        $bodies  = $this->bodies($node);
        $regions = [];

        foreach ($this->catalogue->for($owner, $pageType) as $id => $slot) {
            $regions[$id] = $this->indent(
                $this->merger->region($id, $bodies[$id] ?? ''),
                str_repeat("\t", $slot['indent'])
            );
        }

        return $regions;
    }

    /**
     * Put the markers where the code around them is.
     *
     * The library renders them with four spaces per level; these files are
     * indented with tabs, and a marker that does not line up reads as debris
     * rather than as an invitation to write there. Only the two marker lines
     * move - the body is whatever somebody wrote in the model, and reindenting
     * that would be editing their code.
     *
     * The markers themselves still come from the library, so there is one
     * statement of what a marker looks like. That matters more than it sounds:
     * a marker written here that drifted from the pattern the merger matches
     * would show up only as somebody's code failing to come back.
     *
     * @since  1.0.0
     */
    private function indent(string $region, string $indent): string
    {
        $lines = explode("\n", $region);
        $last  = \count($lines) - 1;

        $lines[0]     = $indent . ltrim($lines[0]);
        $lines[$last] = $indent . ltrim($lines[$last]);

        return implode("\n", $lines);
    }

    /**
     * The code stored against each slot of one object.
     *
     * Two entries naming the same slot would be two bodies for one place. The
     * later one wins, the same way the last write to a path wins, and
     * `ProjectValidator` refuses the model before it gets here - so this is the
     * behaviour for a model that reached generation another way, not a rule.
     *
     * @return array<string, string>
     *
     * @since  1.0.0
     */
    public function bodies(?object $node): array
    {
        $bodies = [];

        foreach ($this->entries($node) as $entry) {
            $slot = $this->stringAt($entry, 'slot');
            $code = $this->stringAt($entry, 'code');

            if ($slot === '' || trim($code) === '' || !$this->catalogue->has($slot)) {
                continue;
            }

            $bodies[$slot] = $code;
        }

        return $bodies;
    }

    /**
     * Slot ids a model object fills that the catalogue does not offer.
     *
     * A slot that was removed from the catalogue, or is spelled wrong, leaves
     * code in the model that will never be emitted anywhere. Nothing else would
     * notice: generation would simply produce a file without it.
     *
     * @return string[]
     *
     * @since  1.0.0
     */
    public function unknownSlots(?object $node): array
    {
        $unknown = [];

        foreach ($this->entries($node) as $entry) {
            $slot = $this->stringAt($entry, 'slot');

            if ($slot !== '' && !$this->catalogue->has($slot)) {
                $unknown[] = $slot;
            }
        }

        return array_values(array_unique($unknown));
    }

    /**
     * The custom-code rows on one object.
     *
     * Stored as a repeating group, so an object keyed `customcode0`,
     * `customcode1` and so on rather than an array - what Joomla's subform
     * field hands back.
     *
     * @return list<object>
     *
     * @since  1.0.0
     */
    private function entries(?object $node): array
    {
        if ($node === null || !property_exists($node, 'customcode') || !\is_object($node->customcode)) {
            return [];
        }

        return array_values(array_filter((array) $node->customcode, \is_object(...)));
    }

    /**
     * @since  1.0.0
     */
    private function stringAt(object $node, string $key): string
    {
        return property_exists($node, $key) && is_scalar($node->{$key}) ? (string) $node->{$key} : '';
    }
}
