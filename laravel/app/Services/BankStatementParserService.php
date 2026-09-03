namespace App\Services;

use App\Models\BankImport;
use App\Models\Transaction;
use League\Csv\Reader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class BankStatementParserService
{
    public function parse(UploadedFile $file, int $userId): BankImport
    {
        return DB::transaction(function () use ($file, $userId) {
            $csv = Reader::createFromPath($file->getRealPath(), 'r');
            $csv->setHeaderOffset(0);

            $bankImport = BankImport::create([
                'filename' => $file->getClientOriginalName(),
                'imported_at' => now(),
                'status' => 'completed',
                'total_rows' => 0,
                'user_id' => $userId,
            ]);

            $rowCount = 0;

            foreach ($csv as $record) {
                // Adjust keys based on your bank CSV headers (e.g., Date, Description, Amount, Type, Reference)
                Transaction::create([
                    'bank_import_id' => $bankImport->id,
                    'transaction_date' => $record['Date'] ?? now(),
                    'description' => $record['Description'] ?? '',
                    'amount' => abs((float) ($record['Amount'] ?? 0)),
                    'type' => strtolower($record['Type'] ?? 'debit'),
                    'reference' => $record['Reference'] ?? null,
                ]);

                $rowCount++;
            }

            $bankImport->update(['total_rows' => $rowCount]);

            return $bankImport;
        });
    }
}