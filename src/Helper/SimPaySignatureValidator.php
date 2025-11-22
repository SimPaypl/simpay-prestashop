<?php

namespace SimPaypl\PrestaShop\Helper;

class SimPaySignatureValidator
{
    public function isValid(array $payload, string $ipnKey): bool
    {
        $data = $this->flattenArray($payload);
        $data[] = $ipnKey;

        $signature = hash('sha256', implode('|', $data));

        return hash_equals($signature, $payload['signature']);
    }

    private function flattenArray(array $array): array
    {
        unset($array['signature']);

        $return = [];

        array_walk_recursive($array, function ($a) use (&$return) {
            $return[] = $a;
        });

        return $return;
    }
}
