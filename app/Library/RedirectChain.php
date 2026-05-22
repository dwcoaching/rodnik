<?php

namespace App\Library;

use App\Models\Spring;
use RuntimeException;

class RedirectChain
{
    public function __construct(
        protected ?Spring $finalTarget,
    ) {
    }

    public static function fromSpring(Spring $source): self
    {
        $spring = $source;
        $seen = [];

        while ($spring->redirect_to_spring_id) {
            if (isset($seen[$spring->id])) {
                throw new RuntimeException("Redirect loop detected for spring #{$source->id}.");
            }

            $seen[$spring->id] = true;
            $spring = Spring::find($spring->redirect_to_spring_id);

            if (! $spring) {
                return new self(null);
            }
        }

        return new self($spring->id === $source->id ? null : $spring);
    }

    public function finalTarget(): ?Spring
    {
        return $this->finalTarget;
    }
}
