<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment; // Importante para los archivos adjuntos

class EmailSupplierTemplateMarkdownMail extends Mailable
{
    use Queueable, SerializesModels;

    // Propiedades públicas para que estén disponibles en la vista blade
    public $content;
    public $customSubject; // Usamos un nombre diferente para no chocar con métodos internos

    // Propiedad para guardar la información del adjunto
    protected $attachmentData;

    /**
     * Create a new message instance.
     *
     * @param string $subject
     * @param string $content
     * @param array $attachmentData
     * @return void
     */
    public function __construct(string $subject, string $content, array $attachmentData)
    {
        $this->customSubject = $subject;
        $this->content = $content;
        $this->attachmentData = $attachmentData;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: $this->customSubject,
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        return new Content(
            // Aquí apuntas a tu vista de Blade. Laravel busca en la carpeta 'resources/views'
            markdown: 'emails.email-supplier-template-markdown',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments()
    {
        // Si no hay datos de adjunto, regresa un array vacío
        if (empty($this->attachmentData)) {
            return [];
        }

        // Crea el adjunto usando la ruta y el nombre del archivo
        return [
            Attachment::fromPath($this->attachmentData['path'])
                ->as($this->attachmentData['name'])
                ->withMime('application/pdf'),
        ];
    }
}
