APP_NAME=drdl

phar:
	composer install --no-dev && rm -rf tests/;
	rm -f ./$(APP_NAME);
	rm -f ./$(APP_NAME).tar.gz;
	phar-composer build;
	mv ./$(APP_NAME).phar ./$(APP_NAME);
	chmod +x ./$(APP_NAME);
	tar -zcvf ./$(APP_NAME).tar.gz ./$(APP_NAME);

.PHONY: phar

