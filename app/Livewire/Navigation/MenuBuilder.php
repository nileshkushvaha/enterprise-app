<?php

declare(strict_types=1);

namespace App\Livewire\Navigation;

use App\Enums\Navigation\NavigationLinkType;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use App\Navigation\DTOs\NavigationItemData;
use App\Navigation\Services\NavigationItemService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MenuBuilder extends Component
{
    // ── Identity ───────────────────────────────────────────────────────────

    public string $navigationId = '';

    public string $navigationName = '';

    // ── Tree state ─────────────────────────────────────────────────────────

    /** @var array<int, array<string, mixed>> */
    public array $treeItems = [];

    // ── Left-panel searches ────────────────────────────────────────────────

    public string $searchPages = '';

    public string $searchPosts = '';

    public string $searchCategories = '';

    public string $searchTags = '';

    // ── Left-panel custom link inputs ──────────────────────────────────────

    public string $customLinkType = 'url';

    public string $routeNameInput = '';

    public string $routeLabel = '';

    public string $customUrlInput = '';

    public string $customUrlLabel = '';

    public string $emailInput = '';

    public string $emailLabel = '';

    public string $phoneInput = '';

    public string $phoneLabel = '';

    public string $anchorInput = '';

    public string $anchorLabel = '';

    // ── Edit slide-over ────────────────────────────────────────────────────

    public bool $showSlideOver = false;

    public ?string $editingItemId = null;

    /** @var array<string, mixed> */
    public array $editForm = [
        'label' => '',
        'icon' => '',
        'target' => '_self',
        'rel' => '',
        'css_class' => '',
        'css_id' => '',
        'badge_text' => '',
        'badge_color' => '',
        'visibility' => 'all',
        'required_role_ids' => [],
        'required_permission_ids' => [],
        'is_active' => true,
        'open_in_modal' => false,
        'locale' => '',
        'publish_from' => '',
        'publish_until' => '',
    ];

    // ── Lifecycle ──────────────────────────────────────────────────────────

    public function mount(string $navigationId): void
    {
        $menu = NavigationMenu::find($navigationId);

        if (! $menu) {
            abort(404);
        }

        $this->navigationId = $menu->id;
        $this->navigationName = $menu->name;
        $this->loadTree();
    }

    // ── Computed properties ────────────────────────────────────────────────

    #[Computed]
    public function pages(): Collection
    {
        $q = Page::query()->select(['id', 'title', 'slug', 'status']);

        if ($this->searchPages !== '') {
            $q->where(function ($sub) {
                $sub->where('title', 'like', '%'.$this->searchPages.'%')
                    ->orWhere('slug', 'like', '%'.$this->searchPages.'%');
            });
        }

        return $q->orderBy('title')->limit(20)->get();
    }

    #[Computed]
    public function posts(): Collection
    {
        $q = Post::query()->select(['id', 'title', 'slug', 'status']);

        if ($this->searchPosts !== '') {
            $q->where(function ($sub) {
                $sub->where('title', 'like', '%'.$this->searchPosts.'%')
                    ->orWhere('slug', 'like', '%'.$this->searchPosts.'%');
            });
        }

        return $q->orderBy('title')->limit(20)->get();
    }

    #[Computed]
    public function categories(): Collection
    {
        $q = PostCategory::query()->select(['id', 'name', 'slug']);

        if ($this->searchCategories !== '') {
            $q->where(function ($sub) {
                $sub->where('name', 'like', '%'.$this->searchCategories.'%')
                    ->orWhere('slug', 'like', '%'.$this->searchCategories.'%');
            });
        }

        return $q->orderBy('name')->limit(20)->get();
    }

    #[Computed]
    public function tags(): Collection
    {
        $q = Tag::query()->select(['id', 'name', 'slug']);

        if ($this->searchTags !== '') {
            $q->where(function ($sub) {
                $sub->where('name', 'like', '%'.$this->searchTags.'%')
                    ->orWhere('slug', 'like', '%'.$this->searchTags.'%');
            });
        }

        return $q->orderBy('name')->limit(20)->get();
    }

    #[Computed]
    public function availableRoles(): Collection
    {
        return Role::orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function availablePermissions(): Collection
    {
        return Permission::orderBy('name')->get(['id', 'name']);
    }

    // ── Add items from left panel ──────────────────────────────────────────

    public function addPage(string $id): void
    {
        $page = Page::find($id);
        if (! $page) {
            return;
        }

        $this->getService()->createForLinkable(
            $this->menu(),
            'page',
            $page->id,
            $page->title,
            NavigationLinkType::Page,
        );

        $this->loadTree();
    }

    public function addPost(string $id): void
    {
        $post = Post::find($id);
        if (! $post) {
            return;
        }

        $this->getService()->createForLinkable(
            $this->menu(),
            'post',
            $post->id,
            $post->title,
            NavigationLinkType::Post,
        );

        $this->loadTree();
    }

    public function addCategory(string $id): void
    {
        $category = PostCategory::find($id);
        if (! $category) {
            return;
        }

        $this->getService()->createForLinkable(
            $this->menu(),
            'category',
            $category->id,
            $category->name,
            NavigationLinkType::Category,
        );

        $this->loadTree();
    }

    public function addTag(string $id): void
    {
        $tag = Tag::find($id);
        if (! $tag) {
            return;
        }

        $this->getService()->createForLinkable(
            $this->menu(),
            'tag',
            $tag->id,
            $tag->name,
            NavigationLinkType::Tag,
        );

        $this->loadTree();
    }

    public function addRoute(): void
    {
        $name = trim($this->routeNameInput);
        $label = trim($this->routeLabel) ?: $name;

        if ($name === '') {
            return;
        }

        $this->getService()->createForUrl(
            $this->menu(),
            NavigationLinkType::Route,
            $name,
            $label,
        );

        $this->routeNameInput = '';
        $this->routeLabel = '';
        $this->loadTree();
    }

    public function addCustomUrl(): void
    {
        $url = trim($this->customUrlInput);
        $label = trim($this->customUrlLabel) ?: $url;

        if ($url === '') {
            return;
        }

        $this->getService()->createForUrl(
            $this->menu(),
            NavigationLinkType::Url,
            $url,
            $label,
        );

        $this->customUrlInput = '';
        $this->customUrlLabel = '';
        $this->loadTree();
    }

    public function addCustomLink(): void
    {
        match ($this->customLinkType) {
            'url'    => $this->addCustomUrl(),
            'route'  => $this->addRoute(),
            'email'  => $this->addEmail(),
            'phone'  => $this->addPhone(),
            'anchor' => $this->addAnchor(),
            default  => null,
        };
    }

    public function addEmail(): void
    {
        $email = trim($this->emailInput);
        $label = trim($this->emailLabel) ?: $email;

        if ($email === '') {
            return;
        }

        $this->getService()->createForUrl(
            $this->menu(),
            NavigationLinkType::Email,
            $email,
            $label,
        );

        $this->emailInput = '';
        $this->emailLabel = '';
        $this->loadTree();
    }

    public function addPhone(): void
    {
        $phone = trim($this->phoneInput);
        $label = trim($this->phoneLabel) ?: $phone;

        if ($phone === '') {
            return;
        }

        $this->getService()->createForUrl(
            $this->menu(),
            NavigationLinkType::Phone,
            $phone,
            $label,
        );

        $this->phoneInput = '';
        $this->phoneLabel = '';
        $this->loadTree();
    }

    public function addAnchor(): void
    {
        $anchor = trim($this->anchorInput);
        $label = trim($this->anchorLabel) ?: $anchor;

        if ($anchor === '') {
            return;
        }

        $this->getService()->createForUrl(
            $this->menu(),
            NavigationLinkType::Anchor,
            $anchor,
            $label,
        );

        $this->anchorInput = '';
        $this->anchorLabel = '';
        $this->loadTree();
    }

    // ── Tree operations ────────────────────────────────────────────────────

    public function editItem(string $id): void
    {
        $item = $this->getService()->findItem($id);

        if (! $item || $item->navigation_id !== $this->navigationId) {
            return;
        }

        $this->editingItemId = $id;
        $this->editForm = [
            'label' => $item->label,
            'icon' => $item->icon ?? '',
            'target' => $item->target,
            'rel' => $item->rel ?? '',
            'css_class' => $item->css_class ?? '',
            'css_id' => $item->css_id ?? '',
            'badge_text' => $item->badge_text ?? '',
            'badge_color' => $item->badge_color ?? '',
            'visibility' => $item->visibility->value,
            'required_role_ids' => $item->roles->pluck('id')->map(fn ($v) => (string) $v)->toArray(),
            'required_permission_ids' => $item->permissions->pluck('id')->map(fn ($v) => (string) $v)->toArray(),
            'is_active' => $item->is_active,
            'open_in_modal' => $item->open_in_modal,
            'locale' => $item->locale ?? '',
            'publish_from' => $item->publish_from?->format('Y-m-d\TH:i') ?? '',
            'publish_until' => $item->publish_until?->format('Y-m-d\TH:i') ?? '',
        ];

        $this->showSlideOver = true;
    }

    public function saveItem(): void
    {
        $this->validate([
            'editForm.label' => ['required', 'string', 'max:255'],
            'editForm.target' => ['required', 'in:_self,_blank,_parent,_top'],
            'editForm.visibility' => ['required', 'string'],
            'editForm.locale' => ['nullable', 'string', 'max:10', 'regex:/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/'],
            'editForm.publish_from' => ['nullable', 'date'],
            'editForm.publish_until' => ['nullable', 'date', 'after:editForm.publish_from'],
        ]);

        if (! $this->editingItemId) {
            return;
        }

        $item = $this->getService()->findItem($this->editingItemId);

        if (! $item || $item->navigation_id !== $this->navigationId) {
            return;
        }

        $data = NavigationItemData::fromArray([
            'label' => $this->editForm['label'],
            'link_type' => $item->link_type->value,
            'url' => $item->url,
            'route_name' => $item->route_name,
            'route_params' => $item->route_params ?? [],
            'linkable_type' => $item->linkable_type,
            'linkable_id' => $item->linkable_id,
            'icon' => $this->editForm['icon'] ?: null,
            'target' => $this->editForm['target'],
            'rel' => $this->editForm['rel'] ?: null,
            'css_class' => $this->editForm['css_class'] ?: null,
            'css_id' => $this->editForm['css_id'] ?: null,
            'badge_text' => $this->editForm['badge_text'] ?: null,
            'badge_color' => $this->editForm['badge_color'] ?: null,
            'visibility' => $this->editForm['visibility'],
            'required_role_ids' => array_map('intval', $this->editForm['required_role_ids'] ?? []),
            'required_permission_ids' => array_map('intval', $this->editForm['required_permission_ids'] ?? []),
            'is_active' => (bool) ($this->editForm['is_active'] ?? true),
            'open_in_modal' => (bool) ($this->editForm['open_in_modal'] ?? false),
            'parent_id' => $item->parent_id,
            'locale' => ($this->editForm['locale'] ?? '') !== '' ? $this->editForm['locale'] : null,
            'publish_from' => ($this->editForm['publish_from'] ?? '') !== '' ? $this->editForm['publish_from'] : null,
            'publish_until' => ($this->editForm['publish_until'] ?? '') !== '' ? $this->editForm['publish_until'] : null,
        ]);

        $this->getService()->update($item, $data);

        $this->showSlideOver = false;
        $this->editingItemId = null;
        $this->loadTree();
    }

    public function cancelEdit(): void
    {
        $this->showSlideOver = false;
        $this->editingItemId = null;
        $this->resetValidation();
    }

    public function duplicateItem(string $id): void
    {
        $item = $this->getService()->findItem($id);

        if (! $item || $item->navigation_id !== $this->navigationId) {
            return;
        }

        $this->getService()->duplicate($item);
        $this->loadTree();
    }

    public function deleteItem(string $id): void
    {
        $item = NavigationItem::where('id', $id)
            ->where('navigation_id', $this->navigationId)
            ->first();

        if (! $item) {
            return;
        }

        $this->getService()->delete($item);
        $this->loadTree();
    }

    /**
     * @param  array<int, array{id: string, parentId: string|null, sortOrder: int}>  $items
     */
    public function reorder(array $items): void
    {
        if (empty($items)) {
            return;
        }

        $this->getService()->reorder($this->menu(), $items);
        $this->loadTree();
    }

    // ── Private ────────────────────────────────────────────────────────────

    private function loadTree(): void
    {
        $this->treeItems = $this->getService()->getTreeArray($this->menu());
        $this->dispatch('navigation-tree-updated');
    }

    private function menu(): NavigationMenu
    {
        return NavigationMenu::findOrFail($this->navigationId);
    }

    private function getService(): NavigationItemService
    {
        return app(NavigationItemService::class);
    }

    public function render(): View
    {
        return view('livewire.navigation.menu-builder');
    }
}
