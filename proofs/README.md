# Proof Images Storage

This folder contains uploaded proof images for deposit transactions.

## File Naming Convention
- Files are named using `uniqid()` + '.jpg' extension
- Example: `507f1f77bcf86cd799439011.jpg`

## Security Notes
- Only authenticated users can upload files
- Files are validated as base64 image data
- Original filenames are not preserved for security

## Cleanup
Consider implementing automatic cleanup of old/unused proof files after transactions are processed.