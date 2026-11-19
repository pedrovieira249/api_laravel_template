<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ServiceClient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * O segredo nunca aparece aqui: ele só é mostrado uma vez, na resposta da criação.
 *
 * @mixin ServiceClient
 */
final class ServiceClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // O id é o client_id que o serviço usa para pedir tokens.
            'id' => $this->id,
            'name' => $this->name,
            'abilities' => $this->abilities,
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
