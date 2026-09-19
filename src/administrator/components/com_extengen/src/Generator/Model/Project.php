<?php

/**
 * @package     Extengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Generator\Model;

use Yepr\Gen\Core\Model\ModelInterface;

/**
 * One project: the description of an extension, as the component stores it.
 *
 * Until now this was a bare stdClass from `json_decode`, produced in thirteen
 * places that each queried the database and decoded the result themselves. Two
 * of those thirteen had even drifted to a different method name for the same
 * five lines. A model that exists as a type has one place to load it, one place
 * to say what it must contain, and somewhere for a question like "what version
 * of this format is it" to live.
 *
 * **The stored shape.** Joomla subforms store repeating groups as an object
 * keyed `datamodel0`, `datamodel1` and so on rather than as a list, so the
 * accessors here hand back plain arrays and the numbering stays where it
 * belongs - in the storage format.
 *
 * **raw() is transitional.** The generators still take the decoded stdClass and
 * walk it themselves; making them consume this type is step 1.4, and doing it
 * here would have meant changing generated output in the same commit that
 * introduced the model, which is exactly what the golden baseline exists to
 * prevent. Until then `raw()` hands them what they already expect, unchanged.
 *
 * @since  0.9.0
 */
final class Project implements ModelInterface
{
    /**
     * The format this class writes.
     *
     * Stored models predate versioning and carry no version at all; those read
     * as 1.0, which is what they are. The number is here so that a change to the
     * stored shape can be made without guessing what an old model meant.
     *
     * @since  0.9.0
     */
    public const CURRENT_VERSION = '1.0';

    /**
     * @param  object  $data          The project as stored, decoded.
     * @param  string  $modelVersion  The format version it was stored in.
     *
     * @since  0.9.0
     */
    private function __construct(
        private readonly object $data,
        private readonly string $modelVersion
    ) {
    }

    /**
     * Read a project from the JSON the component stores in `form_data`.
     *
     * @throws \JsonException  When the stored value is not JSON.
     *
     * @since  0.9.0
     */
    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

        if (!\is_object($decoded)) {
            throw new \InvalidArgumentException('A project must be a JSON object, got ' . get_debug_type($decoded) . '.');
        }

        return self::fromObject($decoded);
    }

    /**
     * Read a project from an already decoded value.
     *
     * @since  0.9.0
     */
    public static function fromObject(object $data): self
    {
        $version = isset($data->modelVersion) && \is_string($data->modelVersion)
            ? $data->modelVersion
            : self::CURRENT_VERSION;

        return new self($data, $version);
    }

    /**
     * Read a project from form data, as the edit form posts it.
     *
     * @param  array<string, mixed>  $data  The submitted form data.
     *
     * @since  0.9.0
     */
    public static function fromArray(array $data): self
    {
        $encoded = json_encode($data, JSON_THROW_ON_ERROR);

        return self::fromJson($encoded);
    }

    /**
     * The format version this project was stored in.
     *
     * @since  0.9.0
     */
    public function modelVersion(): string
    {
        return $this->modelVersion;
    }

    /**
     * The project's own name, which is not the component's name.
     *
     * @since  0.9.0
     */
    public function name(): string
    {
        return (string) ($this->data->name ?? '');
    }

    /**
     * The component name, without the `com_` prefix.
     *
     * @since  0.9.0
     */
    public function componentName(): string
    {
        return (string) ($this->component()->component_name ?? '');
    }

    /**
     * The component node: manifest, sections, languages and the rest.
     *
     * @since  0.9.0
     */
    public function component(): object
    {
        return $this->data->extensions->component ?? new \stdClass();
    }

    /**
     * The manifest information: copyright, licence, version, author, namespace.
     *
     * @since  0.9.0
     */
    public function manifest(): object
    {
        return $this->component()->manifest ?? new \stdClass();
    }

    /**
     * The entities, as a list.
     *
     * @return object[]
     *
     * @since  0.9.0
     */
    public function entities(): array
    {
        return self::listOf($this->data->datamodel ?? null);
    }

    /**
     * The pages, as a list.
     *
     * @return object[]
     *
     * @since  0.9.0
     */
    public function pages(): array
    {
        return self::listOf($this->data->pages ?? null);
    }

    /**
     * The languages the component is generated for, as a list.
     *
     * @return object[]
     *
     * @since  0.9.0
     */
    public function languages(): array
    {
        return self::listOf($this->component()->languages ?? null);
    }

    /**
     * The page references per section, keyed by section name.
     *
     * A section says which pages appear where: `backendsection`,
     * `frontendsection`, and the single-page defaults beside them. Only the
     * repeating ones are returned - a default page is one reference and cannot
     * repeat.
     *
     * @return array<string, object[]>
     *
     * @since  0.9.0
     */
    public function sections(): array
    {
        $sections = [];

        foreach (['backendsection', 'frontendsection'] as $name) {
            $references = self::listOf($this->component()->Sections->{$name} ?? null);

            if ($references !== []) {
                $sections[$name] = $references;
            }
        }

        return $sections;
    }

    /**
     * The project exactly as stored.
     *
     * Transitional: the generators walk this directly. See the class comment.
     *
     * @since  0.9.0
     */
    public function raw(): object
    {
        return $this->data;
    }

    /**
     * Turn a subform group into a list, whichever way it was stored.
     *
     * @return object[]
     *
     * @since  0.9.0
     */
    private static function listOf(mixed $value): array
    {
        if (\is_array($value)) {
            return array_values(array_filter($value, '\is_object'));
        }

        if (\is_object($value)) {
            return array_values(array_filter(get_object_vars($value), '\is_object'));
        }

        return [];
    }
}
