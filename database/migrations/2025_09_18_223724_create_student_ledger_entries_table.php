<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('student_ledger_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->date('entry_date');                        // ngày hạch toán
            $t->string('type', 50);                        // 'invoice','payment','adjustment','refund','transfer_fee','discount','writeoff'
            $t->string('ref_type', 100)->nullable();       // 'invoices','payments',...
            $t->unsignedBigInteger('ref_id')->nullable();  // id tham chiếu (nếu có)
            $t->decimal('debit', 12, 2)->default(0);       // tăng công nợ
            $t->decimal('credit', 12, 2)->default(0);      // giảm công nợ
            $t->string('note')->nullable();
            $t->json('meta')->nullable();                  // chi tiết thêm (branch, class, invoice_item,…)
            $t->timestamps();

            $t->index(['student_id', 'entry_date']);
            $t->index(['ref_type','ref_id']);
            $t->index(['type']);
        });
    }
    public function down(): void { Schema::dropIfExists('student_ledger_entries'); }
};
