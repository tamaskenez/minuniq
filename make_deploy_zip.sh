#!/usr/bin/env bash
rm -rf deploy.zip
cd www
zip -r9 ../deploy.zip *
