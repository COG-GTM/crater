<?php

namespace Crater\Http\Resources\Concerns;

use Illuminate\Support\Collection;

trait ChecksRelations
{
    /**
     * Whether the relation has related records, using the eager-loaded
     * relation when present instead of running an exists() query.
     */
    protected function hasRelated(string $relation): bool
    {
        if ($this->resource->relationLoaded($relation)) {
            $related = $this->resource->getRelation($relation);

            return $related instanceof Collection ? $related->isNotEmpty() : ! is_null($related);
        }

        return $this->resource->{$relation}()->exists();
    }
}
