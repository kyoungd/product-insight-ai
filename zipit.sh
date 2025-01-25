cd ..

sudo zip -r h2-product-insight.zip h2-product-insight --exclude "*/.*" --exclude "h2-product-insight/*.sh" --exclude "h2-product-insight/languages/*.sh" --exclude "h2-product-insight/make_one.txt"

sudo chmod 777 h2-product-insight.zip
sudo mv -f h2-product-insight.zip ~/Documents
cd h2-product-insight
echo "Zip file created and moved to ~/Documents"