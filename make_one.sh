#!/bin/bash

# Create or clear the output file - force overwrite if exists
> make_one.txt

# Function to add file contents with header
add_file_content() {
    echo "=== File: $1 ===" >> make_one.txt
    echo "" >> make_one.txt
    cat "$1" >> make_one.txt
    echo "" >> make_one.txt
    echo "=== End of $1 ===" >> make_one.txt
    echo "" >> make_one.txt
}

# Find and process PHP files in current directory
find . -maxdepth 1 -name "*.php" -type f -not -name "make_one.php" | while read file; do
    add_file_content "$file"
done

# Find and process files in js subdirectory
if [ -d "js" ]; then
    find ./js/ -type f -name "*.js" | while read file; do
        add_file_content "$file"
    done
fi

# Find and process files in includes subdirectory
if [ -d "includes" ]; then
    find ./includes/ -type f -name "*.php" | while read file; do
        add_file_content "$file"
    done
fi
