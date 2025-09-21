<?php

namespace App\Filament\Pages;

use App\Imports\BranchStockImport;
use App\Models\Branch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;

class ImportBranchStock extends Page
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationGroup = 'Katalog';
    protected static ?string $navigationLabel = 'Stok Güncelle (Şube)';
    protected static ?string $title           = 'Stok Güncelle (Şube)';
    protected static ?string $breadcrumb      = 'Stok Güncelle (Şube)';

    protected static string $view = 'filament.pages.import-branch-stock';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public array $data = [
        'branch_id' => null,
        'file'      => null,
    ];

    public function mount(): void
    {
        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('branch_id')
                    ->label('Şube')
                    ->options(fn () => Branch::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->native(false),

                Forms\Components\FileUpload::make('file')
                    ->label('Excel / CSV')
                    ->helperText('Beklenen sütunlar: sku, stock. Yalnızca seçilen şubenin stoğu güncellenir. SKU gerekli.')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'text/csv',
                    ])
                    ->required()
                    ->storeFiles(false),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $state     = $this->form->getState();
        $branchId  = (int) ($state['branch_id'] ?? 0);
        $file      = $state['file'] ?? null;

        if (! $branchId) {
            Notification::make()->title('Lütfen bir şube seçin.')->danger()->send();
            return;
        }

        if (! $file instanceof TemporaryUploadedFile) {
            Notification::make()->title('Lütfen bir dosya seçin.')->danger()->send();
            return;
        }

        try {
            $import = new BranchStockImport($branchId);
            Excel::import($import, $file->getRealPath());

            // clear only the file field, keep the selected branch
            $state['file'] = null;
            $this->form->fill($state);

            Notification::make()
                ->title('Stok güncelleme tamamlandı')
                ->body("Başarılı: {$import->ok} • Atlanan: {$import->skipped}")
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
