<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channel Authorization — T046 (Princípio II)
|--------------------------------------------------------------------------
|
| Canais privados de tenant. NÃO emitimos eventos nesta fase — apenas
| registramos a authorization para que, quando começarmos a publicar
| (notificações, presença), o isolamento já esteja garantido.
|
| Convenção:
|   - `tenant.{tenantId}`             → broadcast para todo o tenant
|     (ex.: anúncios da clínica). User precisa pertencer ao tenant.
|   - `tenant.{tenantId}.user.{userId}` → broadcast direcionado a um
|     usuário específico, escopado ao tenant. Garante que User não
|     consegue subscrever em canal de outro tenant nem do user errado.
|
| O canal default `App.Models.User.{id}` (notificações Laravel) fica
| inalterado — mas valida pertencimento por id, não por tenant.
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('tenant.{tenantId}', function ($user, $tenantId) {
    return $user !== null
        && $user->tenant_id !== null
        && (int) $user->tenant_id === (int) $tenantId;
});

Broadcast::channel('tenant.{tenantId}.user.{userId}', function ($user, $tenantId, $userId) {
    return $user !== null
        && $user->tenant_id !== null
        && (int) $user->tenant_id === (int) $tenantId
        && (int) $user->id === (int) $userId;
});
