<?php

declare(strict_types=1);

namespace App\Domain\Item;

use App\Support\CycleChecker;

final class BomTreeLoader
{
    public function __construct(private readonly BomSourceInterface $source)
    {
    }

    /** @param list<int> $path */
    public function load(int $variantId, array $path = []): BomNode
    {
        if (in_array($variantId, $path, true)) {
            throw new CycleDetectedException(sprintf('BOM cycle through variant #%d.', $variantId));
        }
        $path[] = $variantId;

        $info = $this->source->variantInfo($variantId);
        $components = array_map(
            fn (array $c): BomComponent => new BomComponent($c['quantity'], $this->load($c['variantId'], $path)),
            $this->source->components($variantId),
        );
        $resources = array_map(
            static fn (array $r): BomResourceLine => new BomResourceLine($r['label'], $r['hours'], $r['ratePerHour']),
            $this->source->resources($variantId),
        );

        return new BomNode($variantId, $info['label'], $info['unitCost'], $components, $resources);
    }

    /** True if adding $componentId under $variantId would make $variantId its own ancestor. */
    public function wouldCreateCycle(int $variantId, int $componentId): bool
    {
        return CycleChecker::wouldCreateCycle(
            $variantId,
            $componentId,
            fn (int $id): array => array_column($this->source->components($id), 'variantId'),
        );
    }
}
