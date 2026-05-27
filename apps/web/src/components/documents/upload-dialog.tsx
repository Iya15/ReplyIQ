'use client';

import { useCallback, useState } from 'react';
import { useDropzone, type FileRejection } from 'react-dropzone';
import { FileText, Upload } from 'lucide-react';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUploadDocument } from '@/hooks/use-documents';
import { cn } from '@/lib/utils';

const ACCEPTED_MIME_TYPES = {
  'application/pdf': ['.pdf'],
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document': ['.docx'],
  'application/msword': ['.doc'],
  'text/plain': ['.txt'],
};

const MAX_BYTES = 25 * 1024 * 1024;

interface UploadDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  chatbotId: string;
}

export function UploadDialog({ open, onOpenChange, chatbotId }: UploadDialogProps) {
  const [file, setFile] = useState<File | null>(null);
  const [title, setTitle] = useState('');
  const [dropError, setDropError] = useState('');
  const upload = useUploadDocument(chatbotId);

  const onDrop = useCallback(
    (accepted: File[], rejected: FileRejection[]) => {
      setDropError('');
      if (rejected.length > 0) {
        const code = rejected[0]?.errors[0]?.code;
        setDropError(
          code === 'file-too-large'
            ? 'File exceeds the 25 MB limit.'
            : 'Unsupported file type. Accepted: PDF, DOCX, TXT.',
        );
        return;
      }
      if (accepted[0]) setFile(accepted[0]);
    },
    [],
  );

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    accept: ACCEPTED_MIME_TYPES,
    maxSize: MAX_BYTES,
    maxFiles: 1,
    multiple: false,
  });

  function handleClose(nextOpen: boolean) {
    if (upload.isPending) return;
    onOpenChange(nextOpen);
    if (!nextOpen) reset();
  }

  function reset() {
    setFile(null);
    setTitle('');
    setDropError('');
    upload.reset();
  }

  async function handleSubmit() {
    if (!file) return;
    const formData = new FormData();
    formData.append('file', file);
    if (title.trim()) formData.append('title', title.trim());
    await upload.mutateAsync(formData);
    onOpenChange(false);
    reset();
  }

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Upload file</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          {/* Drop zone */}
          <div
            {...getRootProps()}
            className={cn(
              'relative flex flex-col items-center justify-center rounded-lg border-2 border-dashed px-6 py-10 cursor-pointer transition-colors',
              isDragActive
                ? 'border-primary bg-primary/5'
                : 'border-muted-foreground/25 hover:border-muted-foreground/50',
              file && 'border-primary/50 bg-primary/5',
            )}
          >
            <input {...getInputProps()} />
            {file ? (
              <div className="flex flex-col items-center gap-2 text-center">
                <FileText className="h-10 w-10 text-primary" />
                <p className="text-sm font-medium">{file.name}</p>
                <p className="text-xs text-muted-foreground">
                  {(file.size / 1024 / 1024).toFixed(2)} MB
                </p>
                <button
                  type="button"
                  className="text-xs text-muted-foreground hover:text-foreground underline"
                  onClick={(e) => {
                    e.stopPropagation();
                    setFile(null);
                  }}
                >
                  Remove
                </button>
              </div>
            ) : (
              <div className="flex flex-col items-center gap-2 text-center">
                <Upload className="h-10 w-10 text-muted-foreground" />
                <p className="text-sm font-medium">
                  {isDragActive ? 'Drop file here' : 'Drag & drop or click to browse'}
                </p>
                <p className="text-xs text-muted-foreground">PDF, DOCX, TXT — up to 25 MB</p>
              </div>
            )}
          </div>

          {dropError && <p className="text-sm text-destructive">{dropError}</p>}

          {/* Optional title override — only shown once a file is selected */}
          {file && (
            <div className="space-y-1.5">
              <Label htmlFor="upload-title">Title (optional)</Label>
              <Input
                id="upload-title"
                placeholder={file.name.replace(/\.[^.]+$/, '')}
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                disabled={upload.isPending}
              />
            </div>
          )}

          {/* Upload progress — actual byte progress tracked via XHR */}
          {upload.isPending && (
            <div className="space-y-1">
              <div className="flex justify-between text-xs text-muted-foreground">
                <span>Uploading…</span>
                <span>{upload.progress}%</span>
              </div>
              <div className="h-1.5 w-full rounded-full bg-muted overflow-hidden">
                <div
                  className="h-full bg-primary rounded-full transition-all duration-150"
                  style={{ width: `${upload.progress}%` }}
                />
              </div>
            </div>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => handleClose(false)} disabled={upload.isPending}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={!file || upload.isPending}>
            {upload.isPending ? 'Uploading…' : 'Upload'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
