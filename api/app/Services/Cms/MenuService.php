<?php

namespace App\Services\Cms;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\User;

/**
 * Construye el árbol de menú ya filtrado por permisos del usuario (la
 * "mejor idea" de reigreengroup: el front no decide qué puede ver).
 */
class MenuService
{
    /** @return array<int, array{label:string, path:string, icon:?string, children:array}> */
    public function treeFor(string $slug, User $user): array
    {
        $menu = Menu::query()->where('slug', $slug)->first();

        if (! $menu) {
            return [];
        }

        return $this->buildTree($menu->items()->whereNull('parent_id')->get(), $user);
    }

    public function slugForAdmin(): string
    {
        return (string) config('tcms.admin_menu', 'admin');
    }

    protected function buildTree($items, User $user): array
    {
        $tree = [];

        foreach ($items as $item) {
            /** @var MenuItem $item */
            if (! $item->is_active) {
                continue;
            }
            if ($item->permission && ! $user->can($item->permission)) {
                continue;
            }

            $children = $item->children->isNotEmpty()
                ? $this->buildTree($item->children, $user)
                : [];

            if ($item->children->isNotEmpty() && $children === []) {
                continue; // rama sin nodos visibles para este usuario
            }

            $tree[] = [
                'label' => $item->label,
                'to' => $item->path,
                'icon' => $item->icon,
                'permission' => $item->permission,
                'children' => $children,
            ];
        }

        return $tree;
    }
}
