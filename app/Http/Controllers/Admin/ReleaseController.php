<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Release;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ReleaseController extends Controller
{
    /**
     * Le disque de stockage pour les releases
     * En production, utiliser 'b2' pour Backblaze
     */
    protected function getStorageDisk(): string
    {
        return config('app.env') === 'production' && config('filesystems.disks.b2.key')
            ? 'b2'
            : 'releases';
    }

    public function index(Product $product): View
    {
        $releases = $product->releases()->latest('published_at')->latest()->paginate(20);

        return view('admin.releases.index', compact('product', 'releases'));
    }

    public function create(Product $product): View
    {
        return view('admin.releases.create', compact('product'));
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'version' => [
                'required',
                'string',
                'max:20',
                'regex:/^\d+\.\d+\.\d+(-[a-zA-Z0-9.]+)?$/',
                "unique:releases,version,NULL,id,product_id,{$product->id}",
            ],
            'changelog' => 'nullable|string',
            'file' => 'required|file|mimes:zip|max:51200', // 50MB max
            'min_php_version' => 'nullable|string|max:10',
            'min_wp_version' => 'nullable|string|max:10',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
        ], [
            'version.regex' => 'La version doit etre au format semver (ex: 1.0.0, 2.1.3-beta)',
            'version.unique' => 'Cette version existe deja pour ce produit.',
            'file.max' => 'Le fichier ne doit pas depasser 50 Mo.',
        ]);

        $file = $request->file('file');
        $disk = $this->getStorageDisk();

        // Generer le chemin du fichier
        $fileName = sprintf('%s-%s.zip', $product->slug, $validated['version']);
        $filePath = sprintf('%s/%s', $product->slug, $fileName);

        // Upload du fichier
        Storage::disk($disk)->put($filePath, file_get_contents($file->getRealPath()));

        // Calculer le hash SHA256
        $fileHash = hash_file('sha256', $file->getRealPath());

        // Gerer la publication
        $isPublished = $request->boolean('is_published');
        $publishedAt = null;

        if ($isPublished) {
            $publishedAt = $validated['published_at']
                ? \Carbon\Carbon::parse($validated['published_at'])
                : now();
        }

        $release = $product->releases()->create([
            'version' => $validated['version'],
            'changelog' => $validated['changelog'],
            'file_path' => $filePath,
            'file_size' => $file->getSize(),
            'file_hash' => $fileHash,
            'min_php_version' => $validated['min_php_version'],
            'min_wp_version' => $validated['min_wp_version'],
            'is_published' => $isPublished,
            'published_at' => $publishedAt,
        ]);

        return redirect()
            ->route('admin.releases.show', [$product, $release])
            ->with('success', 'Release creee avec succes.');
    }

    public function show(Product $product, Release $release): View
    {
        return view('admin.releases.show', compact('product', 'release'));
    }

    public function edit(Product $product, Release $release): View
    {
        return view('admin.releases.edit', compact('product', 'release'));
    }

    public function update(Request $request, Product $product, Release $release): RedirectResponse
    {
        $validated = $request->validate([
            'changelog' => 'nullable|string',
            'min_php_version' => 'nullable|string|max:10',
            'min_wp_version' => 'nullable|string|max:10',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        // Gerer la publication
        $isPublished = $request->boolean('is_published');
        $publishedAt = $release->published_at;

        if ($isPublished && !$release->is_published) {
            // Premiere publication
            $publishedAt = $validated['published_at']
                ? \Carbon\Carbon::parse($validated['published_at'])
                : now();
        } elseif ($isPublished && $validated['published_at']) {
            // Modifier la date de publication programmee
            $publishedAt = \Carbon\Carbon::parse($validated['published_at']);
        } elseif (!$isPublished) {
            // Depublier (garder la date pour reference)
        }

        $release->update([
            'changelog' => $validated['changelog'],
            'min_php_version' => $validated['min_php_version'],
            'min_wp_version' => $validated['min_wp_version'],
            'is_published' => $isPublished,
            'published_at' => $publishedAt,
        ]);

        return redirect()
            ->route('admin.releases.show', [$product, $release])
            ->with('success', 'Release mise a jour avec succes.');
    }

    public function destroy(Product $product, Release $release): RedirectResponse
    {
        // Supprimer le fichier
        $disk = $this->getStorageDisk();
        if ($release->file_path && Storage::disk($disk)->exists($release->file_path)) {
            Storage::disk($disk)->delete($release->file_path);
        }

        $release->delete();

        return redirect()
            ->route('admin.releases.index', $product)
            ->with('success', 'Release supprimee avec succes.');
    }

    /**
     * Telecharger le fichier de la release (pour admin)
     */
    public function download(Product $product, Release $release)
    {
        $disk = $this->getStorageDisk();

        if (!$release->file_path || !Storage::disk($disk)->exists($release->file_path)) {
            return back()->with('error', 'Fichier introuvable.');
        }

        $fileName = sprintf('%s-%s.zip', $product->slug, $release->version);

        return Storage::disk($disk)->download($release->file_path, $fileName);
    }
}
