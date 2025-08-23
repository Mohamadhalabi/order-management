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
    protected static ?string $navigationGroup = 'Catalog';
    protected static ?string $title = 'Update Stock';
    protected static string $view = 'filament.pages.import-products';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole('admin') ?? false;
    }

    public static function canAccess(): bool
    {
        // Same check used when visiting the page URL directly
        return static::shouldRegisterNavigation();
    }
    // Keep form state untyped so Livewire is happy
    public array $data = [
        'file' => null,
        'createNew' => true,
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
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'text/csv',
                    ])
                    ->required()
                    // keep file in tmp so we get a TemporaryUploadedFile
                    ->storeFiles(false),


            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $state = $this->form->getState();
        $file  = $state['file'] ?? null;

        if (! $file) {
            Notification::make()->title('Please choose a file.')->danger()->send();
            return;
        }

        // Expect a TemporaryUploadedFile because storeFiles(false) is set
        if (! $file instanceof TemporaryUploadedFile) {
            Notification::make()
                ->title('Invalid upload state')
                ->body('Please choose the file again and retry.')
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

            // clear only the file field
            $state['file'] = null;
            $this->form->fill($state);

            Notification::make()
                ->title('Products imported / updated')
                ->success()
                ->send();

        } catch (\Throwable $e) {
            Notification::make()
                ->title('Import failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
