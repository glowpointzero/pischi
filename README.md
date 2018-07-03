# pischi

## Wat this?

This package offers automated, recursive file/directory-replacing ('fusing')
on command line level. Nothing a file system couldn't do, right? *BUT*:
before replacing any files in the target directory, previously generated
hashes of the target file(s) can be compared, and files will only be
replaced, if the current file hashes match (depending on configuration).