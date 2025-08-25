<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ProductsImport;

class ImportProducts extends Page
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-arrow-up-tray';

    // 🔽 Menüyü tek grupta toplamak için hepsini "Katalog" yapın
    protected static ?string $navigationGroup = 'Katalog';

    // 🔽 Türkçe başlıklar
    protected static ?string $navigationLabel = 'Stok Güncelle';
    protected static ?string $title           = 'Stok Güncelle';
    protected static ?string $breadcrumb      = 'Stok Güncelle';

    protected static string $view = 'filament.pages.import-products';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    // Livewire form state
    public array $data = [
        'file'       => null,
        'createNew'  => true,
        'updateMeta' => true,
    ];

    public function mount(): void
    {
        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('file')
                    ->label('Excel / CSV')
                    ->helperText('Dosyalarınızı sürükleyip bırakın ya da Göz atın.')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'text/csv',
                    ])
                    ->required()
                    // tmp’te kalsın ki TemporaryUploadedFile gelsin
                    ->storeFiles(false),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $state = $this->form->getState();
        $file  = $state['file'] ?? null;

        if (! $file) {
            Notification::make()->title('Lütfen bir dosya seçin.')->danger()->send();
            return;
        }

        if (! $file instanceof TemporaryUploadedFile) {
            Notification::make()
                ->title('Geçersiz yükleme durumu')
                ->body('Lütfen dosyayı tekrar seçip yeniden deneyin.')
                ->danger()
                ->send();
            return;
        }

        try {
            Excel::import(
                new ProductsImport(
                    createNew: (bool) ($state['createNew'] ?? true),
                    updateMeta: (bool) ($state['updateMeta'] ?? true),
                ),
                $file->getRealPath()
            );

            // yalnızca dosya alanını temizle
            $state['file'] = null;
            $this->form->fill($state);

            Notification::make()
                ->title('Ürünler içe aktarıldı / güncellendi')
                ->success()
                ->send();

        } catch (\Throwable $e) {
            Notification::make()
                ->title('İçe aktarma başarısız')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
