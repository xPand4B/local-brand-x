# File System Watcher - Assessment Task

## Goal

Develop a file system watcher that triggers specific actions when files are created, modified, or deleted. The application must be **easily extendable** to support additional watcher types in the future.

---

## Requirements

### 1. Change Monitoring

* Monitor and log:

  * Newly created files
  * Modified files
  * Deleted files

### 2. JPG Optimization

* On JPG creation:
  Optimize for the web (reduce file size while maintaining quality).

### 3. JSON Processing

* On JSON creation or modification:
  Send the file content via **HTTP POST** to:
  `https://fswatcher.requestcatcher.com/`
  (You can visit the URL to verify it’s working.)

### 4. TXT Processing

* On TXT creation or modification:
  Fetch random text from the **Bacon Ipsum API**:
  `https://baconipsum.com/api/?type=meat-and-filler`
  Append the fetched text to the end of the file.

### 5. ZIP Extraction

* On ZIP creation:
  Automatically extract the archive, apply watchers on content, delete archive

### 6. Anti-Delete Meme Replacement

* On file deletion:
  Replace the deleted file with a random meme from the **Meme API**:
  `https://meme-api.com/gimme`

---

## Technical Details

* Must use **Laravel** or **Symfony**
* Additional tools/libraries allowed

---

## Deliverables

1. **Git repository** with source code (omit vendor code)
2. **Implementation document** describing:

   * Conceptual design
   * Challenges/problems encountered
   * Alternative approaches considered (implemented or not)
   * Chosen solutions and rationale
   * Extensibility considerations for future watchers
