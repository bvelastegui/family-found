<?php

namespace App\Models;

use Database\Factories\EvidenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $path
 * @property string $mime
 * @property string $original_name
 * @property int $size
 * @property string $sha256
 */
#[Fillable(['user_id', 'uploaded_by_id', 'path', 'original_name', 'mime', 'size', 'sha256', 'created_at'])]
#[Hidden(['path', 'sha256'])]
class Evidence extends Model
{
    /** @use HasFactory<EvidenceFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'evidences';
}
