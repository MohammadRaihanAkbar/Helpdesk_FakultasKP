<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Enums\UserType;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\Faculty;
use App\Models\Notification;
use App\Models\PingLog;
use App\Models\Rating;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Semua seeder M.Raihan berjalan di SQLite (test) dan datanya terbaca lewat model & relasinya.
 * Di MySQL/MariaDB dicek manual dengan: php artisan migrate:fresh --seed
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Berkas demo (lampiran & bukti) ditulis ke disk palsu, bukan storage/app/private sungguhan.
        Storage::fake('local');

        // Jalankan DatabaseSeeder sebelum setiap test di class ini.
        // (Properti $seed = true hanya berlaku saat migrasi PERTAMA dalam satu proses test,
        // jadi untuk SQLite in-memory lebih aman memanggil seed() langsung.)
        $this->seed();
    }

    public function test_seeders_create_the_demo_data(): void
    {
        $this->assertSame(6, Faculty::count());
        $this->assertSame(75, Category::count()); // 72 standar + 3 tambahan contoh FITK
        $this->assertSame(3, Category::where('is_standard', false)->count());
        $this->assertSame(1, User::where('role', Role::SuperAdmin)->count());
        $this->assertSame(6, User::where('role', Role::Admin)->count());
        $this->assertSame(6, User::where('role', Role::Staff)->count());
        $this->assertSame(1, User::where('role', Role::Staff)->where('is_active', false)->count());
        $this->assertSame(11, User::where('role', Role::EndUser)->count()); // 4 akun demo + 7 pelapor data laporan (M4)
        $this->assertSame(13, Ticket::where('description', 'not like', 'Data riwayat contoh%')->count()); // tiket contoh utama
        $this->assertGreaterThan(60, Ticket::count()); // + riwayat beberapa bulan (M4)
        $this->assertSame(5, Rating::first()->score);
    }

    public function test_seeded_staff_queue_matches_specialties(): void
    {
        $staffAkademik = User::where('email', 'staff.akademik.fitk@uinssc.test')->first();
        $staffAlumni = User::where('email', 'staff.alumni.fitk@uinssc.test')->first();

        // Tiket demo menunggu di kelompok Sistem Akademik & Jadwal Mengajar masuk antrean staff akademik (tertua dulu),
        // bukan antrean staff alumni.
        $this->assertSame(
            ['Nilai Bahasa Arab belum keluar', 'Jadwal mengajar Kamis bentrok dengan rapat prodi', 'KRS saya belum di-ACC Dosen PA'],
            Ticket::forStaff($staffAkademik)->where('status', TicketStatus::Menunggu)->orderBy('created_at')->pluck('title')->all()
        );
        // Antrean staff alumni: 1 tiket legalisir transkrip yang sudah melewati SLA (M4).
        $this->assertSame(['Legalisir transkrip untuk melamar CPNS belum diproses'],
            Ticket::forStaff($staffAlumni)->where('status', TicketStatus::Menunggu)->pluck('title')->all());
    }

    public function test_demo_ticket_relations_can_be_read_through_models(): void
    {
        // Tiket demo "diproses": punya 1 pesan publik + 1 internal note, 1 Ping, 1 notifikasi in-app.
        $inProgress = Ticket::where('title', 'Wi-Fi ruang dosen lantai 2 mati')->firstOrFail();
        $this->assertSame(2, $inProgress->comments()->count());
        $this->assertSame(1, $inProgress->comments()->where('is_internal_note', true)->count());
        $this->assertSame('staff.umum.fitk@uinssc.test', $inProgress->handler->email);
        $this->assertSame('admin.fitk@uinssc.test', $inProgress->pingLogs->first()->admin->email);
        $this->assertSame(1, $inProgress->handler->inAppNotifications()->where('ticket_id', $inProgress->id)->count());

        // Tiket demo "selesai": bukti penyelesaian + rating dari pelapor.
        $done = Ticket::where('title', 'Nilai Statistika di transkrip tidak sesuai')->firstOrFail();
        $this->assertSame('application/pdf', $done->resolutionProofs->first()->mime_type);
        $this->assertTrue($done->rating->user->is($done->reporter));
        $this->assertSame(UserType::Alumni, $done->reporter_type);

        // Tiket demo "menunggu": lampiran form awal, kategori leaf di bawah "Sistem Akademik & Perkuliahan".
        $waiting = Ticket::where('title', 'KRS saya belum di-ACC Dosen PA')->firstOrFail();
        $this->assertSame(1, $waiting->attachments()->count());
        $this->assertSame('Sistem Akademik & Perkuliahan', $waiting->category->group()->name);
        $this->assertNull($waiting->handler);
    }

    public function test_demo_tickets_follow_the_business_rules(): void
    {
        foreach (Ticket::with('category.parent', 'reporter', 'handler')->get() as $ticket) {
            $label = $ticket->ticket_number;

            $this->assertTrue($ticket->category->isLeaf(), "{$label}: kategori harus leaf");
            $this->assertTrue($ticket->category->isVisibleTo($ticket->reporter_type), "{$label}: audiens cocok");
            $this->assertSame($ticket->faculty_id, $ticket->category->faculty_id, "{$label}: fakultas cocok");
            $this->assertSame($ticket->reporter->user_type, $ticket->reporter_type, "{$label}: snapshot jenis pelapor");
            $this->assertSame('TKT-'.$ticket->created_at->format('Ym'), substr($ticket->ticket_number, 0, 10), "{$label}: bulan nomor");
            $this->assertSame($ticket->status->isOpen(), $ticket->resolved_at === null, "{$label}: resolved_at");

            // Alur PRD 4.5: hanya tiket Menunggu yang belum punya penangan.
            $waiting = $ticket->status === TicketStatus::Menunggu;
            $this->assertSame($waiting, $ticket->handler_id === null, "{$label}: handler_id");
            $this->assertSame($waiting, $ticket->assigned_at === null, "{$label}: assigned_at");
            $this->assertSame($ticket->status === TicketStatus::Ditolak, $ticket->rejection_reason !== null, "{$label}: alasan penolakan");
            $this->assertSame($ticket->status === TicketStatus::Selesai, $ticket->resolutionProofs()->exists(), "{$label}: bukti penyelesaian");
            if ($ticket->rating) {
                $this->assertSame(TicketStatus::Selesai, $ticket->status, "{$label}: rating hanya untuk tiket Selesai");
            }
            if ($ticket->first_responded_at) {
                $this->assertTrue($ticket->first_responded_at->gte($ticket->assigned_at), "{$label}: respons setelah diambil");
            }

            if ($ticket->handler) {
                $this->assertTrue(Ticket::forStaff($ticket->handler)->whereKey($ticket->id)->exists(), "{$label}: handler punya spesialisasinya");
            }
        }

        // Berkas demo benar-benar ada di disk privat, jadi tombol unduh tidak 404.
        foreach (Attachment::all() as $attachment) {
            Storage::disk('local')->assertExists($attachment->file_path);
        }
    }

    public function test_demo_data_covers_m2_screens(): void
    {
        $mahasiswa = User::where('email', 'mahasiswa@uinssc.test')->first();

        $this->assertSame(8, $mahasiswa->reportedTickets()->count());
        $this->assertTrue($mahasiswa->reportedTickets()->get()->contains->isRejectedForWrongTarget()); // Buat Ulang Tiket
        $this->assertSame(1, $mahasiswa->reportedTickets()->whereHas('comments.attachments')->count()); // balasan berlampiran

        // Integritas Dosen hanya dipegang staff Dekanat; identitas pelapornya tidak terlihat oleh staff lain.
        $sensitive = Ticket::whereHas('category', fn ($q) => $q->where('name', 'Integritas Dosen'))->firstOrFail();
        $this->assertSame(['staff.dekanat.fitk@uinssc.test'], Category::find($sensitive->category_id)->staff()->pluck('email')->all());
        $this->assertTrue(Gate::forUser(User::where('email', 'admin.fitk@uinssc.test')->first())->allows('viewReporterIdentity', $sensitive));
        $this->assertFalse(Gate::forUser(User::where('email', 'staff.dekanat.fitk@uinssc.test')->first())->allows('viewReporterIdentity', $sensitive));
    }

    public function test_demo_data_covers_m3_screens(): void
    {
        $akademik = User::where('email', 'staff.akademik.fitk@uinssc.test')->first();
        $queue = Ticket::forStaff($akademik)->where('status', TicketStatus::Menunggu)->get();

        // Antrean staff akademik punya tiket merah, kuning, dan aman.
        $this->assertEqualsCanonicalizing(['overdue', 'near', 'ok'], $queue->map->slaState()->all());

        // Tiket Saya staff akademik: 1 diproses yang belum dibalas (untuk mencoba first_responded_at).
        $mine = $akademik->handledTickets()->where('status', TicketStatus::Diproses)->get();
        $this->assertCount(1, $mine);
        $this->assertNull($mine->first()->first_responded_at);

        // Pelapor punya tiket Selesai yang belum dirating, dan ada tiket Selesai yang melewati SLA.
        $mahasiswa = User::where('email', 'mahasiswa@uinssc.test')->first();
        $this->assertTrue($mahasiswa->reportedTickets()->where('status', TicketStatus::Selesai)->doesntHave('rating')->exists());
        $this->assertTrue(Ticket::where('status', TicketStatus::Selesai)->get()->contains(fn (Ticket $t) => $t->slaMet() === false));
        $this->assertTrue(Ticket::where('status', TicketStatus::Selesai)->get()->contains(fn (Ticket $t) => $t->slaMet() === true));
    }

    public function test_demo_data_covers_m4_screens(): void
    {
        $fitk = Faculty::where('code', 'FITK')->value('id');
        $febi = Faculty::where('code', 'FEBI')->value('id');

        // Riwayat beberapa bulan untuk grafik & laporan, di FITK dan FEBI.
        $months = Ticket::where('faculty_id', $fitk)->get()->map(fn (Ticket $t) => $t->created_at->format('Y-m'))->unique();
        $this->assertGreaterThanOrEqual(4, $months->count());
        $this->assertGreaterThan(5, Ticket::where('faculty_id', $febi)->count());

        // Ada tiket Selesai yang sesuai SLA dan yang melewati SLA, ada yang Ditolak, rating bervariasi.
        $closed = Ticket::where('faculty_id', $fitk)->closed()->get();
        $this->assertTrue($closed->contains(fn (Ticket $t) => $t->slaMet() === true));
        $this->assertTrue($closed->contains(fn (Ticket $t) => $t->slaMet() === false));
        $this->assertTrue($closed->contains(fn (Ticket $t) => $t->status === TicketStatus::Ditolak));
        $this->assertGreaterThan(1, Rating::distinct()->count('score'));

        // Dashboard admin: minimal 2 tiket menganggur (Menunggu + lewat SLA), beberapa Ping, dan teguran belum dibaca.
        $idle = Ticket::where('faculty_id', $fitk)->where('status', TicketStatus::Menunggu)->get()->filter(fn (Ticket $t) => $t->slaState() === 'overdue');
        $this->assertGreaterThanOrEqual(2, $idle->count());
        $this->assertGreaterThanOrEqual(5, PingLog::count());
        $akademik = User::where('email', 'staff.akademik.fitk@uinssc.test')->first();
        $this->assertSame(1, $akademik->inAppNotifications()->where('is_read', false)->count());

        // Setiap Ping demo tercatat juga sebagai notifikasi in-app untuk staff penerimanya.
        $this->assertSame(PingLog::count(), Notification::where('type', 'in_app')->where('subject', 'like', 'Ping dari Admin%')->count());

        // Nomor tiket tetap urut sesuai tanggal dibuat dalam setiap bulan.
        foreach (Ticket::orderBy('created_at')->orderBy('id')->get()->groupBy(fn (Ticket $t) => $t->created_at->format('Ym')) as $tickets) {
            $this->assertSame(range(1, $tickets->count()), $tickets->map(fn (Ticket $t) => (int) substr($t->ticket_number, -4))->all());
        }
    }

    public function test_demo_data_covers_m5_screens(): void
    {
        // Super Admin + Admin di setiap fakultas; dosen demo memakai NIP (kolom nip).
        $this->assertSame(1, User::where('role', Role::SuperAdmin)->count());
        $this->assertSame(Faculty::count(), User::where('role', Role::Admin)->distinct()->count('faculty_id'));
        $this->assertSame('198804122015031001', User::where('email', 'dosen@uinssc.test')->value('nip'));
        $this->assertSame(0, User::where('user_type', UserType::Dosen)->whereNull('nip')->count());

        // Pelapor demo punya balasan Staff yang belum dibaca -> "Balasan baru" di dashboard.
        $mahasiswa = User::where('email', 'mahasiswa@uinssc.test')->first();
        $unread = $mahasiswa->inAppNotifications()->where('is_read', false)->get();
        $this->assertCount(1, $unread);
        $this->assertStringStartsWith('Balasan baru dari Staff: TKT-', $unread->first()->subject);
        $this->assertSame($mahasiswa->id, $unread->first()->ticket->reporter_id);

        $this->actingAs($mahasiswa)->get('/dashboard')->assertOk()->assertSee('Balasan baru')->assertSee('Performa SLA Fakultas');
        $this->actingAs(User::where('role', Role::SuperAdmin)->first())->get('/super-admin/dashboard')->assertOk()->assertSee('Celah Coverage Staff');
    }
}
