cd ..

if [[ "$1" == "--full" ]]; then
    sudo zip -r h2-product-insight.zip h2-product-insight --exclude "*/.*"
else
    sudo zip -r h2-product-insight.zip h2-product-insight --exclude "*/.*" --exclude "h2-product-insight/assets/*"
fi

sudo chmod 777 h2-product-insight.zip
sudo mv -f h2-product-insight.zip ~/Documents
cd h2-product-insight
echo "Zip file created and moved to ~/Documents"