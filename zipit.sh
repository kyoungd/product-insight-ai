cd ..
sudo zip -r h2-product-insight.zip h2-product-insight --exclude "*/.*"
sudo chmod 777 h2-product-insight.zip
sudo mv -f h2-product-insight.zip ~/Documents
cd h2-product-insight
echo "Zip file created and moved to ~/Documents"
