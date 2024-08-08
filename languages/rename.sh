for file in wc-odoo-integration-*.po
do
    mv "$file" "${file/wc-odoo-integration/wc2odoo}"
done
