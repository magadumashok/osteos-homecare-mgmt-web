<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores uploads under "<homecare_id>/<directory>/<file>" on the documents
 * disk and returns their public URL, in the layout the legacy app used
 * (e.g. "<homecare>/patients/<patient>/documents/report.pdf").
 *
 * Replaces the legacy copy-to-public/, push-to-S3, delete-folder sequence
 * that was repeated in every upload endpoint.
 */
class FileStorageService
{
    public function __construct(private readonly TenantService $tenants) {}

    /** Store an uploaded file under its (sanitised) original name. */
    public function storeUpload(UploadedFile $file, string $directory): string
    {
        $name = $this->safeName($file->getClientOriginalName(), $file->guessExtension() ?: 'bin');

        $this->disk()->putFileAs($this->path($directory), $file, $name);

        return $this->url($directory, $name);
    }

    /** @param  list<UploadedFile>  $files @return list<string> */
    public function storeUploads(array $files, string $directory): array
    {
        return array_map(fn (UploadedFile $file) => $this->storeUpload($file, $directory), $files);
    }

    /**
     * Store base64 content (signatures from the drawing pad, recorded audio).
     * A "data:...;base64," prefix is accepted. PNGs are checked to be images.
     */
    public function storeBase64(string $content, string $directory, string $name): string
    {
        $content = preg_replace('/^data:[^;]+;base64,/', '', trim($content));
        $binary = base64_decode($content, true);

        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages(['file' => 'The uploaded content is not valid base64.']);
        }

        if (str_ends_with(strtolower($name), '.png') && @getimagesizefromstring($binary) === false) {
            throw ValidationException::withMessages(['image' => 'The signature is not a valid image.']);
        }

        $name = $this->safeName($name, pathinfo($name, PATHINFO_EXTENSION) ?: 'bin');
        $this->disk()->put($this->path($directory).'/'.$name, $binary);

        return $this->url($directory, $name);
    }

    /** Store generated content (e.g. a PDF built on the server) under $name. */
    public function put(string $content, string $directory, string $name): string
    {
        $name = $this->safeName($name, pathinfo($name, PATHINFO_EXTENSION) ?: 'bin');
        $this->disk()->put($this->path($directory).'/'.$name, $content);

        return $this->url($directory, $name);
    }

    /**
     * The bytes of a stored file, by the URL it was saved under: read from
     * the documents disk, or downloaded when it lives elsewhere (older
     * uploads). Null when it cannot be read.
     */
    public function read(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        try {
            $relative = $this->relativeUrl($url);
            if ($relative !== null) {
                return $this->disk()->get(rawurldecode($relative));
            }

            $response = Http::timeout(15)->get($url);

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * "https://<documents url>/<homecare>/users/x/file.pdf" → "<homecare>/users/x/file.pdf"
     * (still URL-encoded), or null for a URL outside the documents store.
     */
    public function relativeUrl(?string $url): ?string
    {
        $base = rtrim((string) config('homecare.documents.url'), '/').'/';

        return $url && str_starts_with($url, $base) ? substr($url, strlen($base)) : null;
    }

    /** "patients/hcp001/documents" → "<homecare>/patients/hcp001/documents". */
    private function path(string $directory): string
    {
        $segments = array_map(
            fn ($segment) => Str::of($segment)->replaceMatches('/[^A-Za-z0-9_.-]+/', '_')->trim('._')->toString(),
            explode('/', trim($directory, '/')),
        );

        return implode('/', array_filter([$this->tenants->current(), ...$segments], fn ($s) => $s !== ''));
    }

    private function url(string $directory, string $name): string
    {
        return rtrim(config('homecare.documents.url'), '/').'/'.$this->path($directory).'/'.rawurlencode($name);
    }

    /** Keep a readable name but drop path parts and characters S3 keys choke on. */
    private function safeName(string $original, string $fallbackExtension): string
    {
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION) ?: $fallbackExtension);
        $base = Str::slug(pathinfo(basename($original), PATHINFO_FILENAME)) ?: Str::random(12);

        return $base.'.'.preg_replace('/[^a-z0-9]/', '', $extension);
    }

    private function disk()
    {
        return Storage::disk(config('homecare.documents.disk'));
    }
}
